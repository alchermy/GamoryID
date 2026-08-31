<?php

namespace Tests\Feature;

use App\Jobs\ProcessInventoryImport;
use App\Models\ImportJob;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use App\Services\CredentialCipher;
use App\Services\TagGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        (new ProcessInventoryImport($job->id))->handle(app(TagGenerator::class), app(CredentialCipher::class));

        $this->assertDatabaseCount('inventory_items', 0);
        $this->assertDatabaseHas('import_jobs', ['id' => $job->id, 'status' => 'failed', 'imported_rows' => 0, 'failed_rows' => 1]);
        $this->assertDatabaseHas('import_errors', ['import_job_id' => $job->id, 'row_number' => 3]);
        Storage::disk('private')->assertMissing($path);
    }
}
