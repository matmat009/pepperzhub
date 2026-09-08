<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingCourier;
use App\Models\ShippingRegion;
use App\Models\User;
use App\Support\SessionCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kit contents are snapshotted onto the order line, not read live.
 *
 * Cross-cutting on purpose: the value is written by storefront checkout and
 * only ever read by the admin detail screen, so testing either half alone
 * would miss the point of the column.
 */
class OrderKitSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private ShippingRegion $region;

    private PaymentMethod $paymentMethod;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethod = PaymentMethod::create([
            'name' => 'GOtyme Bank',
            'details' => [['label' => 'Bank', 'value' => 'GOtyme Bank']],
            'is_active' => true,
        ]);

        $courier = ShippingCourier::create(['name' => 'J&T Express', 'is_active' => true]);

        $this->region = $courier->regions()->create([
            'name' => 'Luzon & Visayas',
            'rate' => 150,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $category = Category::create(['name' => 'Healing', 'slug' => 'healing']);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'BPC-157',
            'slug' => 'bpc-157',
            'status' => 'active',
            'short_description' => 'Peptide.',
        ]);
    }

    /** @param array<int, string> $inclusions */
    private function variant(bool $isKit, array $inclusions = []): ProductVariant
    {
        return $this->product->variants()->create([
            'label' => $isKit ? 'Starter kit' : '5mg vial',
            'price' => 2450,
            'stock' => 10,
            'is_kit' => $isKit,
            'kit_inclusions' => $inclusions,
            'sort_order' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'name' => 'Juan Dela Cruz',
            'social_handle' => 'fb.com/juandc',
            'phone' => '0917 123 4567',
            'street' => '12 Mabini St',
            'barangay' => 'San Antonio',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'zip' => '1203',
            'notes' => null,
            'shipping_region_id' => $this->region->id,
            'payment_method_id' => $this->paymentMethod->id,
            'payment_proof' => UploadedFile::fake()->image('receipt.jpg'),
        ];
    }

    private function checkout(ProductVariant $variant): OrderItem
    {
        Storage::fake('local');

        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 1]])
            ->post(route('storefront.checkout.store'), $this->payload())
            ->assertRedirect();

        return Order::firstOrFail()->items()->firstOrFail();
    }

    public function test_ordering_a_kit_captures_its_flag_and_inclusions(): void
    {
        $inclusions = ['5mg BPC-157 vial', 'Bacteriostatic water 30ml', 'Insulin syringes x10'];

        $item = $this->checkout($this->variant(true, $inclusions));

        $this->assertTrue($item->is_kit);
        $this->assertSame($inclusions, $item->kit_inclusions);
    }

    public function test_ordering_a_non_kit_captures_false_and_an_empty_list(): void
    {
        $item = $this->checkout($this->variant(false));

        $this->assertFalse($item->is_kit);
        $this->assertSame([], $item->kit_inclusions);
    }

    /**
     * The whole reason the columns exist: the variant is free to change, the
     * order line is not.
     */
    public function test_editing_the_variant_afterwards_does_not_rewrite_the_order(): void
    {
        $variant = $this->variant(true, ['Vial', 'Water']);

        $item = $this->checkout($variant);

        $variant->update([
            'kit_inclusions' => ['Vial', 'Water', 'Swabs', 'Syringes'],
        ]);

        $this->assertSame(['Vial', 'Water'], $item->fresh()->kit_inclusions);
    }

    public function test_admin_detail_receives_the_snapshot_for_a_kit_line(): void
    {
        $inclusions = ['5mg BPC-157 vial', 'Alcohol swabs x20'];

        $order = $this->checkout($this->variant(true, $inclusions))->order;

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('order.items.0.is_kit', true)
                    ->where('order.items.0.kit_inclusions', $inclusions)
            );
    }

    /**
     * A line written before the columns existed. Both stay null — "never
     * captured", which is not the same as "not a kit" — and the condition on
     * the detail screen (`is_kit && kit_inclusions?.length`) renders no list
     * for either that or a plain non-kit line.
     */
    public function test_a_line_predating_the_snapshot_stays_null(): void
    {
        $order = $this->checkout($this->variant(false))->order;

        // Straight to the columns, bypassing the cast, exactly as an untouched
        // pre-migration row would look.
        $order->items()->update(['is_kit' => null, 'kit_inclusions' => null]);

        $item = $order->items()->firstOrFail();

        $this->assertNull($item->is_kit);
        $this->assertNull($item->kit_inclusions);

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('order.items.0.is_kit', null)
                    ->where('order.items.0.kit_inclusions', null)
            );
    }
}
