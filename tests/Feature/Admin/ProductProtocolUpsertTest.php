<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductTechnicalDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Protocol notes are rows in product_technical_details under a third type, so
 * they ride the same id-preserving upsert as purity and storage rather than a
 * parallel implementation. These lock that in, and lock in that editing one
 * type never disturbs another.
 *
 * @see ProductRelationUpsertTest for the purity and storage equivalents.
 */
class ProductProtocolUpsertTest extends TestCase
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
            'dosage' => '250-500mcg',
            'frequency' => 'Once daily',
            'duration' => '4-6 weeks',
        ]);

        $product->technicalDetails()->create([
            'type' => ProductTechnicalDetail::TYPE_PURITY,
            'label' => 'HPLC',
            'value' => '99.2%',
            'sort_order' => 0,
        ]);

        foreach ([
            'Reconstitute with 2ml bacteriostatic water.',
            'Rotate injection sites.',
        ] as $index => $note) {
            $product->technicalDetails()->create([
                'type' => ProductTechnicalDetail::TYPE_PROTOCOL,
                'label' => null,
                'value' => $note,
                'sort_order' => $index,
            ]);
        }

        return $product->fresh(['technicalDetails']);
    }

    /**
     * Exactly what the form posts back for an unchanged product.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Product $product, array $overrides = []): array
    {
        $ofType = fn (string $type): array => $product->technicalDetails
            ->where('type', $type)
            ->map(fn ($detail) => [
                'id' => $detail->id,
                'label' => $detail->label,
                'value' => $detail->value,
            ])
            ->values()
            ->all();

        $base = [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'status' => $product->status,
            'featured' => $product->featured,
            'short_description' => $product->short_description,
            'full_description' => $product->full_description,
            'dosage' => $product->dosage,
            'frequency' => $product->frequency,
            'duration' => $product->duration,
            'variants' => [],
            'purity' => $ofType(ProductTechnicalDetail::TYPE_PURITY),
            'storage' => $ofType(ProductTechnicalDetail::TYPE_STORAGE),
            'protocol' => $ofType(ProductTechnicalDetail::TYPE_PROTOCOL),
            'kept_image_ids' => [],
        ];

        return [...$base, ...$overrides];
    }

    /** @return array<int, ProductTechnicalDetail> */
    private function notes(Product $product): array
    {
        return $product->technicalDetails
            ->where('type', ProductTechnicalDetail::TYPE_PROTOCOL)
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    public function test_editing_a_protocol_note_preserves_every_note_id(): void
    {
        $product = $this->product();
        $notes = $this->notes($product);
        $idsBefore = array_map(fn ($note) => $note->id, $notes);

        $payload = $this->payload($product);
        $payload['protocol'][0]['value'] = 'Reconstitute with 3ml bacteriostatic water.';

        $this->put(route('admin.products.update', $product), $payload)
            ->assertSessionHasNoErrors();

        $product->refresh()->load('technicalDetails');

        $this->assertSame(
            $idsBefore,
            array_map(fn ($note) => $note->id, $this->notes($product)),
            'Protocol note ids changed during an edit that only touched the text.',
        );
        $this->assertDatabaseHas('product_technical_details', [
            'id' => $idsBefore[0],
            'type' => ProductTechnicalDetail::TYPE_PROTOCOL,
            'label' => null,
            'value' => 'Reconstitute with 3ml bacteriostatic water.',
        ]);
        // The untouched neighbour kept its value as well as its id.
        $this->assertDatabaseHas('product_technical_details', [
            'id' => $idsBefore[1],
            'value' => 'Rotate injection sites.',
        ]);
    }

    public function test_editing_a_protocol_note_leaves_the_purity_row_alone(): void
    {
        $product = $this->product();
        $purity = $product->technicalDetails
            ->firstWhere('type', ProductTechnicalDetail::TYPE_PURITY);

        $payload = $this->payload($product);
        $payload['protocol'][0]['value'] = 'Swirl, never shake.';

        $this->put(route('admin.products.update', $product), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_technical_details', [
            'id' => $purity->id,
            'type' => ProductTechnicalDetail::TYPE_PURITY,
            'label' => 'HPLC',
            'value' => '99.2%',
        ]);
    }

    public function test_removing_one_protocol_note_deletes_only_that_row(): void
    {
        $product = $this->product();
        $idsBefore = array_map(fn ($note) => $note->id, $this->notes($product));

        $payload = $this->payload($product);
        array_shift($payload['protocol']);

        $this->put(route('admin.products.update', $product), $payload)
            ->assertSessionHasNoErrors();

        $product->refresh()->load('technicalDetails');

        $this->assertDatabaseMissing('product_technical_details', ['id' => $idsBefore[0]]);
        $this->assertSame(
            [$idsBefore[1]],
            array_map(fn ($note) => $note->id, $this->notes($product)),
        );
    }

    public function test_a_new_note_is_inserted_without_disturbing_the_existing_ids(): void
    {
        $product = $this->product();
        $idsBefore = array_map(fn ($note) => $note->id, $this->notes($product));

        $payload = $this->payload($product);
        // A note added in the form arrives with a null id.
        $payload['protocol'][] = ['id' => null, 'value' => 'Discard after 30 days.'];

        $this->put(route('admin.products.update', $product), $payload)
            ->assertSessionHasNoErrors();

        $product->refresh()->load('technicalDetails');
        $idsAfter = array_map(fn ($note) => $note->id, $this->notes($product));

        $this->assertCount(3, $idsAfter);
        $this->assertSame($idsBefore, array_slice($idsAfter, 0, 2));
        $this->assertSame('Discard after 30 days.', $this->notes($product)[2]->value);
    }

    public function test_the_three_protocol_fields_are_saved_on_the_product(): void
    {
        $product = $this->product();

        $this->put(route('admin.products.update', $product), $this->payload($product, [
            'dosage' => '500mcg',
            'frequency' => 'Twice daily',
            'duration' => '8 weeks',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'dosage' => '500mcg',
            'frequency' => 'Twice daily',
            'duration' => '8 weeks',
        ]);
    }

    /** Blank fields are stored as null, so they never count as "has a protocol". */
    public function test_blank_protocol_fields_are_stored_as_null(): void
    {
        $product = $this->product();

        $this->put(route('admin.products.update', $product), $this->payload($product, [
            'dosage' => '   ',
            'frequency' => '',
            'duration' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'dosage' => null,
            'frequency' => null,
            'duration' => null,
        ]);
    }
}
