<?php

namespace Tests\Feature;

use App\Jobs\ProcessInventoryImport;
use App\Models\ImportJob;
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
