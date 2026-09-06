<?php

namespace Tests\Unit;

use App\Exceptions\TagConflictException;
use App\Models\InventoryItem;
use App\Models\Shop;
use App\Services\TagGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function shop(array $attributes = []): Shop
    {
        return Shop::create(array_merge([
            'name' => 'Pornchai Service',
            'slug' => 'pornchai-'.uniqid(),
            'status' => 'trialing',
        ], $attributes));
    }

    public function test_prefix_comes_from_the_shop_setting_or_the_name(): void
    {
        $tags = app(TagGenerator::class);

        $this->assertStringStartsWith('POR-', $tags->generate($this->shop()));
        $this->assertStringStartsWith('PCX-', $tags->generate($this->shop(['tag_prefix' => 'pcx'])));
        $this->assertStringStartsWith('GID-', $tags->generate($this->shop(['name' => 'ร้านพรชัย'])));
    }

    public function test_auto_numbers_run_sequentially_per_shop_and_ignore_legacy_tags(): void
    {
        $tags = app(TagGenerator::class);
        $shop = $this->shop(['tag_prefix' => 'ABC']);
        InventoryItem::create(['shop_id' => $shop->id, 'tag' => '23DX5', 'title' => 'legacy', 'cost' => 0, 'list_price' => 0, 'status' => 'available']);

        $first = $tags->generate($shop);
        InventoryItem::create(['shop_id' => $shop->id, 'tag' => $first, 'title' => 'a', 'cost' => 0, 'list_price' => 0, 'status' => 'available']);
        $second = $tags->generate($shop);

        $this->assertSame('ABC-0001', $first);
        $this->assertSame('ABC-0002', $second);
    }

    public function test_a_shop_supplied_number_is_used_and_padded(): void
    {
        $tags = app(TagGenerator::class);
        $shop = $this->shop(['tag_prefix' => 'PCX']);

        $this->assertSame('PCX-1282', $tags->generate($shop, '1282'));
        $this->assertSame('PCX-0007', $tags->generate($shop, '7'));
        $this->assertSame('PCX-A12', $tags->generate($shop, 'a12'));
    }

    public function test_a_duplicate_shop_supplied_number_is_rejected(): void
    {
        $tags = app(TagGenerator::class);
        $shop = $this->shop(['tag_prefix' => 'PCX']);
        InventoryItem::create(['shop_id' => $shop->id, 'tag' => 'PCX-1282', 'title' => 'a', 'cost' => 0, 'list_price' => 0, 'status' => 'available']);

        $this->expectException(TagConflictException::class);
        $tags->generate($shop, '1282');
    }

    public function test_retag_swaps_the_prefix_keeps_the_number_and_skips_legacy_and_collisions(): void
    {
        $tags = app(TagGenerator::class);
        $shop = $this->shop(['tag_prefix' => 'XYZ']);
        $mk = fn (string $tag) => InventoryItem::create(['shop_id' => $shop->id, 'tag' => $tag, 'title' => $tag, 'cost' => 0, 'list_price' => 0, 'status' => 'available']);
        $a = $mk('ABC-0001');
        $mk('ABC-0002');
        $mk('23DX5');          // legacy — untouched
        $mk('XYZ-0002');       // target of ABC-0002 already exists → that one is skipped

        $this->assertSame(2, $tags->retaggableCount($shop)); // ABC-0001, ABC-0002
        $result = $tags->retagShop($shop);

        $this->assertSame(['renamed' => 1, 'skipped' => 1], $result);
        $this->assertSame('XYZ-0001', $a->fresh()->tag);
        $this->assertDatabaseHas('inventory_items', ['tag' => '23DX5']);
        $this->assertDatabaseHas('inventory_items', ['tag' => 'ABC-0002']); // skipped, left as-is
    }
}
