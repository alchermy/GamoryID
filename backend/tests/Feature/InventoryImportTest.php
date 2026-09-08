<?php

namespace Tests\Feature;

use App\Jobs\ProcessInventoryImport;
use App\Jobs\SendDiscordShopNotification;
use App\Models\ImportError;
use App\Models\ImportJob;
use App\Models\InventoryItem;
use App\Models\Reservation;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use App\Services\CredentialCipher;
use App\Services\InventoryImportReader;
use App\Services\TagGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_row_rolls_back_the_entire_csv_batch(): void
    {
        Queue::fake();
        Storage::fake('private');
        $shop = Shop::create(['name' => 'ร้านนำเข้า', 'slug' => 'import-'.uniqid(), 'status' => 'trialing']);
        $user = User::create(['name' => 'เจ้าของร้าน', 'email' => 'import@example.test', 'password' => 'password', 'current_shop_id' => $shop->id]);
        ShopMember::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => 'owner', 'permissions' => []]);
        $path = "imports/{$shop->id}/batch.csv";
        Storage::disk('private')->put($path, "title,list_price,username\nไอดีที่ถูกต้อง,5000,one@example.test\nไอดีราคาผิด,abc,two@example.test\n");
        $job = ImportJob::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'queued',
            'disk' => 'private',
            'path' => $path,
            'mapping' => ['title' => 'title', 'list_price' => 'list_price', 'username' => 'username'],
            'total_rows' => 2,
        ]);

        (new ProcessInventoryImport($job->id))->handle(
            app(TagGenerator::class),
            app(CredentialCipher::class),
            app(InventoryImportReader::class),
        );

        $this->assertDatabaseCount('inventory_items', 0);
        $this->assertDatabaseHas('import_jobs', ['id' => $job->id, 'status' => 'failed', 'imported_rows' => 0, 'failed_rows' => 1]);
        $this->assertDatabaseHas('import_errors', ['import_job_id' => $job->id, 'row_number' => 3]);
        Storage::disk('private')->assertMissing($path);
        Queue::assertPushed(
            SendDiscordShopNotification::class,
            fn (SendDiscordShopNotification $job) => $job->purpose === 'inventory'
                && $job->title === 'นำเข้าข้อมูลไอดีไม่สำเร็จ',
        );
    }

    public function test_verified_merchant_can_download_the_excel_template(): void
    {
        [$user, $shop] = $this->verifiedMerchant();

        $response = $this->actingAs($user)
            ->withHeader('X-Shop-Id', $shop->id)
            ->get('/api/v1/imports/template');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString(
            'GamoryID-inventory-import-template.xlsx',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_excel_template_can_be_previewed_with_password_masked(): void
    {
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        $template = resource_path('templates/gamoryid-inventory-import-template.xlsx');

        $response = $this->actingAs($user)
            ->withHeader('X-Shop-Id', $shop->id)
            ->post('/api/v1/imports/preview', [
                'file' => new UploadedFile(
                    $template,
                    'gamoryid-template.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true,
                ),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.total_rows', 1)
            ->assertJsonPath('data.rows.0.username', 'example.user01')
            ->assertJsonPath('data.rows.0.password', '••••••••')
            ->assertJsonPath('data.headers.6', 'list_price')
            ->assertJsonPath('data.headers.7', 'notes');
    }

    public function test_excel_template_can_be_confirmed_and_imported(): void
    {
        Storage::fake('private');
        Queue::fake();
        [$user, $shop] = $this->verifiedMerchant();
        $template = resource_path('templates/gamoryid-inventory-import-template.xlsx');

        $preview = $this->actingAs($user)
            ->withHeader('X-Shop-Id', $shop->id)
            ->post('/api/v1/imports/preview', [
                'file' => new UploadedFile($template, 'inventory.xlsx', null, null, true),
            ])
            ->assertCreated();

        $importId = (int) $preview->json('data.id');
        $this->actingAs($user)
            ->withHeader('X-Shop-Id', $shop->id)
            ->postJson("/api/v1/imports/{$importId}/confirm", [
                'mapping' => [
                    'username' => 'username',
                    'password' => 'password',
                    'description' => 'description',
                    'rank' => 'rank',
                    'level' => 'level',
                    'cost' => 'cost',
                    'list_price' => 'list_price',
                    'notes' => 'notes',
                ],
            ])
            ->assertAccepted();

        Queue::assertPushed(ProcessInventoryImport::class, fn (ProcessInventoryImport $job) => $job->importJobId === $importId);

        (new ProcessInventoryImport($importId))->handle(
            app(TagGenerator::class),
            app(CredentialCipher::class),
            app(InventoryImportReader::class),
        );

        $this->assertDatabaseHas('inventory_items', [
            'shop_id' => $shop->id,
            'title' => '',
            'username' => 'example.user01',
            'list_price' => 3900,
            'notes' => 'ตัวอย่าง: ลูกค้ากำลังพิจารณา',
        ]);
        $this->assertDatabaseHas('import_jobs', [
            'id' => $importId,
            'status' => 'completed',
            'imported_rows' => 1,
        ]);
    }

    public function test_a_large_batch_rolls_back_entirely_when_the_last_row_is_invalid(): void
    {
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        $rows = "title,list_price,username\n";
        for ($i = 1; $i <= 299; $i++) {
            $rows .= "ไอดีที่ {$i},5000,user{$i}@example.test\n";
        }
        $rows .= "ไอดีราคาผิด,abc,user300@example.test\n";
        $path = "imports/{$shop->id}/large-batch.csv";
        Storage::disk('private')->put($path, $rows);
        $job = ImportJob::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'queued',
            'disk' => 'private',
            'path' => $path,
            'mapping' => ['title' => 'title', 'list_price' => 'list_price', 'username' => 'username'],
            'total_rows' => 300,
        ]);

        (new ProcessInventoryImport($job->id))->handle(
            app(TagGenerator::class),
            app(CredentialCipher::class),
            app(InventoryImportReader::class),
        );

        $this->assertDatabaseCount('inventory_items', 0);
        $this->assertDatabaseHas('import_jobs', ['id' => $job->id, 'status' => 'failed', 'imported_rows' => 0, 'failed_rows' => 1]);
        $this->assertDatabaseHas('import_errors', ['import_job_id' => $job->id, 'row_number' => 301]);
        Storage::disk('private')->assertMissing($path);
    }

    public function test_a_username_repeated_in_the_file_is_skipped_not_fatal(): void
    {
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        $path = "imports/{$shop->id}/duplicate-username.csv";
        Storage::disk('private')->put($path, "title,list_price,username\nไอดีที่หนึ่ง,5000,same.user\nไอดีที่สอง,6000,same.user\nไอดีที่สาม,7000,other.user\n");
        $job = ImportJob::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'queued',
            'disk' => 'private',
            'path' => $path,
            'mapping' => ['title' => 'title', 'list_price' => 'list_price', 'username' => 'username'],
            'total_rows' => 3,
        ]);

        (new ProcessInventoryImport($job->id))->handle(
            app(TagGenerator::class),
            app(CredentialCipher::class),
            app(InventoryImportReader::class),
        );

        // first "same.user" + "other.user" import; the repeat is skipped
        $this->assertDatabaseCount('inventory_items', 2);
        $this->assertDatabaseHas('import_jobs', [
            'id' => $job->id, 'status' => 'completed', 'imported_rows' => 2, 'skipped_rows' => 1, 'failed_rows' => 0,
        ]);
        $skip = ImportError::where('import_job_id', $job->id)->where('kind', 'duplicate')->firstOrFail();
        $this->assertSame(3, $skip->row_number);
        $this->assertStringContainsString('ซ้ำกับแถว 2 ในไฟล์', $skip->message);
    }

    public function test_a_username_already_in_the_shop_is_skipped_and_the_rest_imports(): void
    {
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        InventoryItem::create([
            'shop_id' => $shop->id, 'tag' => 'HAVE1', 'title' => 'มีอยู่แล้ว',
            'username' => 'Taken.User', 'cost' => 1, 'list_price' => 100, 'status' => 'available',
        ]);
        $path = "imports/{$shop->id}/existing-username.csv";
        Storage::disk('private')->put($path, "title,list_price,username\nไอดีใหม่,5000,taken.user\nไอดีอีกอัน,6000,fresh.user\n");
        $job = ImportJob::create([
            'shop_id' => $shop->id, 'user_id' => $user->id, 'status' => 'queued',
            'disk' => 'private', 'path' => $path,
            'mapping' => ['title' => 'title', 'list_price' => 'list_price', 'username' => 'username'],
            'total_rows' => 2,
        ]);

        (new ProcessInventoryImport($job->id))->handle(
            app(TagGenerator::class), app(CredentialCipher::class), app(InventoryImportReader::class),
        );

        // "taken.user" already exists -> skipped; "fresh.user" imports
        $this->assertDatabaseCount('inventory_items', 2);
        $this->assertDatabaseHas('inventory_items', ['title' => 'ไอดีอีกอัน', 'username' => 'fresh.user']);
        $this->assertDatabaseMissing('inventory_items', ['title' => 'ไอดีใหม่']);
        $this->assertDatabaseHas('import_jobs', [
            'id' => $job->id, 'status' => 'completed', 'imported_rows' => 1, 'skipped_rows' => 1, 'failed_rows' => 0,
        ]);
        $this->assertStringContainsString('มีอยู่ในคลังแล้ว', ImportError::where('import_job_id', $job->id)->where('kind', 'duplicate')->firstOrFail()->message);
    }

    public function test_a_username_that_only_clashes_with_a_sold_item_still_imports(): void
    {
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        InventoryItem::create([
            'shop_id' => $shop->id, 'tag' => 'SOLD1', 'title' => 'ขายไปแล้ว',
            'username' => 'Resell.User', 'cost' => 1, 'list_price' => 100, 'status' => 'sold',
        ]);
        $path = "imports/{$shop->id}/resell-username.csv";
        Storage::disk('private')->put($path, "title,list_price,username\nนำกลับมาขาย,5000,resell.user\n");
        $job = ImportJob::create([
            'shop_id' => $shop->id, 'user_id' => $user->id, 'status' => 'queued',
            'disk' => 'private', 'path' => $path,
            'mapping' => ['title' => 'title', 'list_price' => 'list_price', 'username' => 'username'],
            'total_rows' => 1,
        ]);

        (new ProcessInventoryImport($job->id))->handle(
            app(TagGenerator::class), app(CredentialCipher::class), app(InventoryImportReader::class),
        );

        // the only clash is a sold item -> the row is imported, nothing skipped
        $this->assertDatabaseCount('inventory_items', 2);
        $this->assertDatabaseHas('inventory_items', ['title' => 'นำกลับมาขาย', 'username' => 'resell.user', 'status' => 'available']);
        $this->assertDatabaseHas('import_jobs', [
            'id' => $job->id, 'status' => 'completed', 'imported_rows' => 1, 'skipped_rows' => 0, 'failed_rows' => 0,
        ]);
    }

    public function test_a_whole_batch_database_failure_is_recorded_and_nothing_is_left_partially_imported(): void
    {
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        InventoryItem::create(['shop_id' => $shop->id, 'tag' => 'DUP01', 'title' => 'มีอยู่แล้ว', 'cost' => 0, 'list_price' => 0, 'status' => 'available']);
        $this->app->bind(TagGenerator::class, fn () => new class extends TagGenerator
        {
            public function generate(Shop $shop, ?string $providedNumber = null, ?int $ignoreItemId = null): string
            {
                return 'DUP01';
            }
        });
        $path = "imports/{$shop->id}/tag-collision.csv";
        Storage::disk('private')->put($path, "title,list_price,username\nไอดีใหม่,5000,collision.user\n");
        $job = ImportJob::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'queued',
            'disk' => 'private',
            'path' => $path,
            'mapping' => ['title' => 'title', 'list_price' => 'list_price', 'username' => 'username'],
            'total_rows' => 1,
        ]);

        (new ProcessInventoryImport($job->id))->handle(
            app(TagGenerator::class),
            app(CredentialCipher::class),
            app(InventoryImportReader::class),
        );

        $this->assertDatabaseCount('inventory_items', 1);
        $this->assertDatabaseHas('import_jobs', ['id' => $job->id, 'status' => 'failed', 'imported_rows' => 0, 'failed_rows' => 1]);
        $error = ImportError::where('import_job_id', $job->id)->firstOrFail();
        $this->assertSame(0, $error->row_number);
        $this->assertStringStartsWith('นำเข้าทั้งชุดไม่สำเร็จ', $error->message);
    }

    public function test_a_valid_csv_batch_is_imported_end_to_end(): void
    {
        Queue::fake();
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        $path = "imports/{$shop->id}/happy-path.csv";
        Storage::disk('private')->put($path, "title,list_price,username,email\nไอดีที่ถูกต้อง 1,5000,csv.user01,acc01@mail.test\nไอดีที่ถูกต้อง 2,6200,csv.user02,acc02@mail.test\n");
        $job = ImportJob::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'queued',
            'disk' => 'private',
            'path' => $path,
            'mapping' => ['title' => 'title', 'list_price' => 'list_price', 'username' => 'username', 'email' => 'email'],
            'total_rows' => 2,
        ]);

        (new ProcessInventoryImport($job->id))->handle(
            app(TagGenerator::class),
            app(CredentialCipher::class),
            app(InventoryImportReader::class),
        );

        $this->assertDatabaseCount('inventory_items', 2);
        $this->assertDatabaseHas('import_jobs', ['id' => $job->id, 'status' => 'completed', 'imported_rows' => 2, 'failed_rows' => 0]);
        $this->assertDatabaseHas('inventory_items', ['title' => 'ไอดีที่ถูกต้อง 1', 'email' => 'acc01@mail.test']);
        $this->assertDatabaseHas('inventory_credentials', ['inventory_item_id' => InventoryItem::where('title', 'ไอดีที่ถูกต้อง 1')->firstOrFail()->id]);
        $this->assertDatabaseHas('activity_logs', ['shop_id' => $shop->id, 'event' => 'import.completed']);
        Storage::disk('private')->assertMissing($path);
        Queue::assertPushed(
            SendDiscordShopNotification::class,
            fn (SendDiscordShopNotification $job) => $job->purpose === 'inventory'
                && $job->title === 'นำเข้าข้อมูลไอดีสำเร็จ'
                && str_contains($job->description, 'เพิ่มเข้าคลัง 2 รายการ')
                && $job->actor === $user->name,
        );
    }

    public function test_a_mapped_id_number_becomes_the_item_code_and_duplicates_are_skipped(): void
    {
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        $shop->update(['tag_prefix' => 'PCX']);
        InventoryItem::create(['shop_id' => $shop->id, 'tag' => 'PCX-1282', 'title' => 'มีอยู่แล้ว', 'username' => 'have.user', 'cost' => 0, 'list_price' => 0, 'status' => 'available']);
        $path = "imports/{$shop->id}/codes.csv";
        Storage::disk('private')->put($path, "no,list_price,username\n1282,5000,new.user\n1295,6000,other.user\n1295,7000,dup.user\n");
        $job = ImportJob::create([
            'shop_id' => $shop->id, 'user_id' => $user->id, 'status' => 'queued', 'disk' => 'private', 'path' => $path,
            'mapping' => ['tag_number' => 'no', 'list_price' => 'list_price', 'username' => 'username'],
            'total_rows' => 3,
        ]);

        (new ProcessInventoryImport($job->id))->handle(
            app(TagGenerator::class), app(CredentialCipher::class), app(InventoryImportReader::class),
        );

        // 1282 clashes with the existing item, the 2nd 1295 clashes within the file → both skipped; 1295 imports
        $this->assertDatabaseHas('inventory_items', ['shop_id' => $shop->id, 'tag' => 'PCX-1295', 'username' => 'other.user']);
        $this->assertDatabaseHas('import_jobs', ['id' => $job->id, 'status' => 'completed', 'imported_rows' => 1, 'skipped_rows' => 2, 'failed_rows' => 0]);
        $this->assertSame(2, ImportError::where('import_job_id', $job->id)->where('kind', 'duplicate')->count());
    }

    public function test_status_column_maps_shop_labels_to_system_statuses(): void
    {
        Queue::fake();
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        $path = "imports/{$shop->id}/with-status.csv";
        Storage::disk('private')->put($path, implode("\n", [
            'title,list_price,cost,username,stat',
            'ขายไปแล้ว,5000,3000,sold.user,ออกแล้ว',
            'ของใหม่,4000,2000,fresh.user,ยังไม่ขาย',
            'ลูกค้าจอง,4500,2500,hold.user,ติดจอง',
            'เลิกทำแล้ว,3000,1000,gone.user,เลิกขาย',
        ])."\n");
        $job = ImportJob::create([
            'shop_id' => $shop->id, 'user_id' => $user->id, 'status' => 'queued', 'disk' => 'private', 'path' => $path,
            'mapping' => ['title' => 'title', 'list_price' => 'list_price', 'cost' => 'cost', 'username' => 'username', 'status' => 'stat'],
            'status_map' => ['ออกแล้ว' => 'sold', 'ยังไม่ขาย' => 'available', 'ติดจอง' => 'reserved', 'เลิกขาย' => 'archived'],
            'total_rows' => 4,
        ]);

        (new ProcessInventoryImport($job->id))->handle(
            app(TagGenerator::class), app(CredentialCipher::class), app(InventoryImportReader::class),
        );

        $this->assertDatabaseHas('inventory_items', ['shop_id' => $shop->id, 'username' => 'sold.user', 'status' => 'sold']);
        $this->assertDatabaseHas('inventory_items', ['shop_id' => $shop->id, 'username' => 'fresh.user', 'status' => 'available']);
        $this->assertDatabaseHas('inventory_items', ['shop_id' => $shop->id, 'username' => 'hold.user', 'status' => 'reserved']);
        $archived = InventoryItem::where('shop_id', $shop->id)->where('username', 'gone.user')->firstOrFail();
        $this->assertSame('archived', $archived->status->value);
        $this->assertNotNull($archived->archived_at);

        // sold ⇒ a Sale row with no customer, priced at list_price
        $soldItem = InventoryItem::where('username', 'sold.user')->firstOrFail();
        $this->assertDatabaseHas('sales', [
            'inventory_item_id' => $soldItem->id, 'customer_id' => null, 'created_by' => $user->id,
            'sold_price' => 5000, 'cost_snapshot' => 3000, 'profit' => 2000,
        ]);
        // reserved ⇒ an open Reservation with no customer and a ~30d expiry
        $reservedItem = InventoryItem::where('username', 'hold.user')->firstOrFail();
        $reservation = Reservation::where('inventory_item_id', $reservedItem->id)->firstOrFail();
        $this->assertNull($reservation->released_at);
        $this->assertNull($reservation->customer_id);
        $this->assertTrue($reservation->expires_at->between(now()->addDays(29), now()->addDays(31)));

        $this->assertDatabaseHas('import_jobs', ['id' => $job->id, 'status' => 'completed', 'imported_rows' => 4, 'skipped_rows' => 0, 'failed_rows' => 0]);
    }

    public function test_status_can_map_to_the_not_yet_listed_status(): void
    {
        Queue::fake();
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        $path = "imports/{$shop->id}/unlisted-status.csv";
        Storage::disk('private')->put($path, "list_price,username,stat\n5000,park.user,ยังไม่เปิดขาย\n");
        $job = ImportJob::create([
            'shop_id' => $shop->id, 'user_id' => $user->id, 'status' => 'queued', 'disk' => 'private', 'path' => $path,
            'mapping' => ['list_price' => 'list_price', 'username' => 'username', 'status' => 'stat'],
            'status_map' => ['ยังไม่เปิดขาย' => 'draft'],
            'total_rows' => 1,
        ]);

        (new ProcessInventoryImport($job->id))->handle(
            app(TagGenerator::class), app(CredentialCipher::class), app(InventoryImportReader::class),
        );

        $item = InventoryItem::where('shop_id', $shop->id)->where('username', 'park.user')->firstOrFail();
        $this->assertSame('draft', $item->status->value);
        $this->assertNull($item->archived_at);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_a_status_value_that_is_not_mapped_falls_back_to_available(): void
    {
        Queue::fake();
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        $path = "imports/{$shop->id}/loose-status.csv";
        Storage::disk('private')->put($path, "list_price,username,stat\n1000,a.user,ออกแล้ว\n1000,b.user,สถานะแปลกๆ\n");
        $job = ImportJob::create([
            'shop_id' => $shop->id, 'user_id' => $user->id, 'status' => 'queued', 'disk' => 'private', 'path' => $path,
            'mapping' => ['list_price' => 'list_price', 'username' => 'username', 'status' => 'stat'],
            'status_map' => ['ออกแล้ว' => 'sold'],
            'total_rows' => 2,
        ]);

        (new ProcessInventoryImport($job->id))->handle(
            app(TagGenerator::class), app(CredentialCipher::class), app(InventoryImportReader::class),
        );

        $this->assertDatabaseHas('inventory_items', ['username' => 'a.user', 'status' => 'sold']);
        $this->assertDatabaseHas('inventory_items', ['username' => 'b.user', 'status' => 'available']);
    }

    public function test_preview_returns_distinct_values_for_low_cardinality_columns(): void
    {
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        $rows = ['list_price,username,stat'];
        // 65 distinct usernames > DISTINCT_CAP (60) so that column is dropped;
        // "stat" stays low-cardinality and is offered for mapping.
        foreach (range(1, 65) as $i) {
            $rows[] = "1000,user{$i}@example.test,".(['ออกแล้ว', 'ยังไม่ขาย', 'ติดจอง'][$i % 3]);
        }
        $file = UploadedFile::fake()->createWithContent('inventory.csv', implode("\n", $rows)."\n");

        $preview = $this->actingAs($user)->withHeader('X-Shop-Id', $shop->id)
            ->post('/api/v1/imports/preview', ['file' => $file])
            ->assertCreated();

        $distinct = $preview->json('data.distinct_values.stat');
        sort($distinct);
        $this->assertSame(['ติดจอง', 'ยังไม่ขาย', 'ออกแล้ว'], $distinct);
        // a high-cardinality column is not offered
        $this->assertArrayNotHasKey('username', $preview->json('data.distinct_values'));
    }

    public function test_confirm_rejects_a_status_map_value_outside_the_enum(): void
    {
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        $file = UploadedFile::fake()->createWithContent('inventory.csv', "list_price,username,stat\n1000,a.user,ออกแล้ว\n");
        $importId = (int) $this->actingAs($user)->withHeader('X-Shop-Id', $shop->id)
            ->post('/api/v1/imports/preview', ['file' => $file])->assertCreated()->json('data.id');

        $this->actingAs($user)->withHeader('X-Shop-Id', $shop->id)
            ->postJson("/api/v1/imports/{$importId}/confirm", [
                'mapping' => ['username' => 'username', 'list_price' => 'list_price', 'status' => 'stat'],
                'status_map' => ['ออกแล้ว' => 'gone'],
            ])
            ->assertStatus(422);
    }

    /** @return array{User, Shop} */
    private function verifiedMerchant(): array
    {
        $shop = Shop::create(['name' => 'ร้านนำเข้า Excel', 'slug' => 'excel-import-'.uniqid(), 'status' => 'trialing', 'trial_ends_at' => now()->addMonth()]);
        $user = User::create([
            'name' => 'เจ้าของร้าน',
            'email' => 'excel-'.uniqid().'@example.test',
            'email_verified_at' => now(),
            'password' => 'password',
            'current_shop_id' => $shop->id,
        ]);
        ShopMember::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'permissions' => [],
        ]);

        return [$user, $shop];
    }
}
