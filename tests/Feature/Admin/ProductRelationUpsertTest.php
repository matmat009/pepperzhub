<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTechnicalDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saving a product used to delete every variant and technical detail and
 * re-insert them, so ids churned on every edit. These lock in the upsert.
 */
class ProductRelationUpsertTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $this->actingAs(User::factory()->create());

        $category = Category::create(['name' => 'Healing', 'slug' => 'healing']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'BPC-157',
            'slug' => 'bpc-157',
            'status' => 'active',
            'short_description' => 'Body protection compound.',
            'full_description' => 'Long description.',
        ]);

        foreach ([
            ['5mg vial', 45.00, 142, false, []],
            ['10mg vial', 79.00, 64, false, []],
            ['Starter kit', 119.00, 12, true, ['Vial', 'Water']],
        ] as $index => [$label, $price, $stock, $isKit, $inclusions]) {
            $product->variants()->create([
                'label' => $label,
                'price' => $price,
                'stock' => $stock,
                'is_kit' => $isKit,
                'kit_inclusions' => $inclusions,
                'sort_order' => $index,
            ]);
        }

        $product->technicalDetails()->create([
            'type' => ProductTechnicalDetail::TYPE_PURITY,
            'label' => 'HPLC',
            'value' => '99.2%',
            'sort_order' => 0,
        ]);
        $product->technicalDetails()->create([
            'type' => ProductTechnicalDetail::TYPE_STORAGE,
            'label' => 'Temperature',
            'value' => '2-8°C',
            'sort_order' => 0,
        ]);

        return $product->fresh(['variants', 'technicalDetails']);
    }

    /**
     * Exactly what the form posts back for an unchanged product.
     *
     * @return array<string, mixed>
     */
    private function payload(Product $product, array $overrides = []): array
    {
        $base = [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'status' => $product->status,
            'featured' => $product->featured,
            'short_description' => $product->short_description,
            'full_description' => $product->full_description,
            'variants' => $product->variants
                ->map(fn ($variant) => [
                    'id' => $variant->id,
                    'label' => $variant->label,
                    'price' => (float) $variant->price,
                    'stock' => $variant->stock,
                    'is_kit' => $variant->is_kit,
                    'kit_inclusions' => $variant->kit_inclusions ?? [],
                ])
                ->values()
                ->all(),
            'purity' => $product->technicalDetails
                ->where('type', ProductTechnicalDetail::TYPE_PURITY)
                ->map(fn ($d) => ['id' => $d->id, 'label' => $d->label, 'value' => $d->value])
                ->values()
                ->all(),
            'storage' => $product->technicalDetails
                ->where('type', ProductTechnicalDetail::TYPE_STORAGE)
                ->map(fn ($d) => ['id' => $d->id, 'label' => $d->label, 'value' => $d->value])
                ->values()
                ->all(),
            'kept_image_ids' => [],
        ];

        return [...$base, ...$overrides];
    }

    public function test_changing_one_variants_price_leaves_every_variant_id_untouched()
    {
        $product = $this->product();
        $idsBefore = $product->variants->pluck('id')->all();
        $targetId = $idsBefore[1];

        $payload = $this->payload($product);
        $payload['variants'][1]['price'] = 99.99;

        $this->put(route('admin.products.update', $product), $payload)
            ->assertSessionHasNoErrors();

        $product->refresh()->load('variants');

        $this->assertSame(
            $idsBefore,
            $product->variants->pluck('id')->all(),
            'Variant ids changed during an edit that only touched a price.',
        );
        $this->assertSame(
            99.99,
            (float) $product->variants->firstWhere('id', $targetId)->price,
        );
        // The untouched neighbours kept their values as well as their ids.
        $this->assertSame(45.00, (float) $product->variants->firstWhere('id', $idsBefore[0])->price);
        $this->assertSame(119.00, (float) $product->variants->firstWhere('id', $idsBefore[2])->price);
    }

    public function test_removing_one_variant_deletes_only_that_row()
    {
        $product = $this->product();
        $idsBefore = $product->variants->pluck('id')->all();
        $removedId = $idsBefore[0];

        $payload = $this->payload($product);
        array_shift($payload['variants']);

        $this->put(route('admin.products.update', $product), $payload)
            ->assertSessionHasNoErrors();

        $product->refresh()->load('variants');

        $this->assertDatabaseMissing('product_variants', ['id' => $removedId]);
        $this->assertSame(
            [$idsBefore[1], $idsBefore[2]],
            $product->variants->pluck('id')->all(),
        );
    }

    public function test_adding_a_variant_inserts_it_without_disturbing_the_others()
    {
        $product = $this->product();
        $idsBefore = $product->variants->pluck('id')->all();

        $payload = $this->payload($product);
        // A row added in the form arrives with a null id.
        $payload['variants'][] = [
            'id' => null,
            'label' => '20mg vial',
            'price' => 145.00,
            'stock' => 30,
            'is_kit' => false,
            'kit_inclusions' => [],
        ];

        $this->put(route('admin.products.update', $product), $payload)
            ->assertSessionHasNoErrors();

        $product->refresh()->load('variants');
        $idsAfter = $product->variants->pluck('id')->all();

        $this->assertCount(4, $idsAfter);
        $this->assertSame($idsBefore, array_slice($idsAfter, 0, 3));
        $this->assertSame('20mg vial', $product->variants->last()->label);
    }

    public function test_technical_detail_ids_survive_an_edit()
    {
        $product = $this->product();
        $purity = $product->technicalDetails->firstWhere('type', ProductTechnicalDetail::TYPE_PURITY);
        $storage = $product->technicalDetails->firstWhere('type', ProductTechnicalDetail::TYPE_STORAGE);

        $payload = $this->payload($product);
        $payload['purity'][0]['value'] = '99.9%';

        $this->put(route('admin.products.update', $product), $payload)
            ->assertSessionHasNoErrors();

        $product->refresh()->load('technicalDetails');

        $this->assertDatabaseHas('product_technical_details', [
            'id' => $purity->id,
            'value' => '99.9%',
        ]);
        $this->assertDatabaseHas('product_technical_details', [
            'id' => $storage->id,
            'value' => '2-8°C',
        ]);
        $this->assertCount(2, $product->technicalDetails);
    }

    public function test_an_id_belonging_to_another_product_is_inserted_rather_than_hijacked()
    {
        $product = $this->product();
        $other = Product::create([
            'category_id' => $product->category_id,
            'name' => 'TB-500',
            'slug' => 'tb-500',
            'status' => 'active',
        ]);
        $foreign = $other->variants()->create([
            'label' => 'Foreign vial',
            'price' => 10.00,
            'stock' => 5,
            'is_kit' => false,
            'kit_inclusions' => [],
            'sort_order' => 0,
        ]);

        $payload = $this->payload($product);
        $payload['variants'][] = [
            'id' => $foreign->id,
            'label' => 'Hijack attempt',
            'price' => 1.00,
            'stock' => 1,
            'is_kit' => false,
            'kit_inclusions' => [],
        ];

        $this->put(route('admin.products.update', $product), $payload)
            ->assertSessionHasNoErrors();

        // The other product's variant is untouched, and the row landed on this
        // product as a new record instead.
        $this->assertSame('Foreign vial', $foreign->refresh()->label);
        $this->assertSame($other->id, $foreign->product_id);
        $this->assertCount(4, $product->refresh()->variants);
    }
}
