<?php

namespace Tests\Feature;

use App\Jobs\ProcessInventoryImport;
use App\Models\ImportError;
use App\Models\ImportJob;
use App\Models\InventoryItem;
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
            ->assertJsonPath('data.rows.0.riot_id', 'Example#TH01')
            ->assertJsonPath('data.rows.0.password', '••••••••')
            ->assertJsonPath('data.headers.7', 'list_price')
            ->assertJsonPath('data.headers.8', 'notes');
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
                    'riot_id' => 'riot_id',
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
            'riot_id' => 'Example#TH01',
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

    public function test_a_duplicate_username_within_the_file_fails_the_whole_batch(): void
    {
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        $path = "imports/{$shop->id}/duplicate-username.csv";
        Storage::disk('private')->put($path, "title,list_price,username\nไอดีที่หนึ่ง,5000,same.user@example.test\nไอดีที่สอง,6000,same.user@example.test\n");
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
        $this->assertDatabaseHas('import_jobs', ['id' => $job->id, 'status' => 'failed', 'imported_rows' => 0]);
        $error = ImportError::where('import_job_id', $job->id)->firstOrFail();
        $this->assertStringContainsString('พบ Username ซ้ำกับแถว', $error->message);
    }

    public function test_a_whole_batch_database_failure_is_recorded_and_nothing_is_left_partially_imported(): void
    {
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        InventoryItem::create(['shop_id' => $shop->id, 'tag' => 'DUP01', 'title' => 'มีอยู่แล้ว', 'cost' => 0, 'list_price' => 0, 'status' => 'available']);
        $this->app->bind(TagGenerator::class, fn () => new class extends TagGenerator
        {
            public function generate(): string
            {
                return 'DUP01';
            }
        });
        $path = "imports/{$shop->id}/tag-collision.csv";
        Storage::disk('private')->put($path, "title,list_price\nไอดีใหม่,5000\n");
        $job = ImportJob::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'queued',
            'disk' => 'private',
            'path' => $path,
            'mapping' => ['title' => 'title', 'list_price' => 'list_price'],
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
        Storage::fake('private');
        [$user, $shop] = $this->verifiedMerchant();
        $path = "imports/{$shop->id}/happy-path.csv";
        Storage::disk('private')->put($path, "title,list_price,username\nไอดีที่ถูกต้อง 1,5000,csv.user01@example.test\nไอดีที่ถูกต้อง 2,6200,csv.user02@example.test\n");
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

        $this->assertDatabaseCount('inventory_items', 2);
        $this->assertDatabaseHas('import_jobs', ['id' => $job->id, 'status' => 'completed', 'imported_rows' => 2, 'failed_rows' => 0]);
        $this->assertDatabaseHas('inventory_credentials', ['inventory_item_id' => InventoryItem::where('title', 'ไอดีที่ถูกต้อง 1')->firstOrFail()->id]);
        Storage::disk('private')->assertMissing($path);
    }

    /** @return array{User, Shop} */
    private function verifiedMerchant(): array
    {
        $shop = Shop::create(['name' => 'ร้านนำเข้า Excel', 'slug' => 'excel-import-'.uniqid(), 'status' => 'trialing']);
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
