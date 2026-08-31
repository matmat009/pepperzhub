<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingCourier;
use App\Models\ShippingRegion;
use App\Support\SessionCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private PaymentMethod $paymentMethod;

    private ShippingRegion $region;

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
            'note' => 'Standard pouch',
            'rate' => 150,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function variant(float $price = 2450, int $stock = 10): ProductVariant
    {
        $category = Category::create(['name' => 'Healing', 'slug' => 'healing']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'BPC-157',
            'slug' => 'bpc-157',
            'status' => 'active',
            'short_description' => 'Peptide.',
        ]);

        return $product->variants()->create([
            'label' => '5mg vial',
            'price' => $price,
            'stock' => $stock,
            'is_kit' => false,
            'sort_order' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }

    public function test_checkout_creates_an_order_and_deducts_stock(): void
    {
        Storage::fake('local');
        $variant = $this->variant(price: 2450, stock: 10);

        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 3]])
            ->post(route('storefront.checkout.store'), $this->payload())
            ->assertRedirect();

        $order = Order::firstOrFail();

        // Redirects to the token URL now, not a flash-backed bare path.
        $this->assertNotEmpty($order->confirmation_token);

        $this->assertSame('unverified', $order->payment_status);
        $this->assertSame('pending', $order->order_status);
        $this->assertSame(1, $order->items()->count());
        // 3 x 2450 = 7350, plus 150 shipping.
        $this->assertSame('7350.00', $order->subtotal);
        $this->assertSame('150.00', $order->shipping_fee);
        $this->assertSame('7500.00', $order->total);

        $this->assertSame(7, (int) $variant->fresh()->stock, 'stock was not deducted');
    }

    public function test_order_number_is_pzh_padded_to_five_digits(): void
    {
        Storage::fake('local');
        $variant = $this->variant();

        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 1]])
            ->post(route('storefront.checkout.store'), $this->payload());

        $order = Order::firstOrFail();

        $this->assertSame('PZH-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT), $order->order_number);
        $this->assertMatchesRegularExpression('/^PZH-\d{5}$/', $order->order_number);
    }

    public function test_price_and_shipping_come_from_the_database_not_the_request(): void
    {
        Storage::fake('local');
        $variant = $this->variant(price: 2450, stock: 5);

        // A tampered client sends its own figures; every one must be ignored.
        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 1]])
            ->post(route('storefront.checkout.store'), $this->payload([
                'subtotal' => 1,
                'total' => 1,
                'shipping_fee' => 0,
                'unit_price' => 1,
            ]));

        $order = Order::firstOrFail();

        $this->assertSame('2450.00', $order->subtotal);
        $this->assertSame('150.00', $order->shipping_fee);
        $this->assertSame('2600.00', $order->total);
        $this->assertSame('2450.00', $order->items()->first()->unit_price);
    }

    public function test_insufficient_stock_rejects_the_whole_order(): void
    {
        Storage::fake('local');
        $variant = $this->variant(stock: 2);

        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 5]])
            ->post(route('storefront.checkout.store'), $this->payload())
            ->assertRedirect(route('storefront.cart'));

        $this->assertSame(0, Order::count(), 'an order was created despite insufficient stock');
        $this->assertSame(2, (int) $variant->fresh()->stock, 'stock changed on a failed checkout');
    }

    public function test_empty_cart_cannot_check_out(): void
    {
        Storage::fake('local');

        $this->withSession([SessionCart::SESSION_KEY => []])
            ->post(route('storefront.checkout.store'), $this->payload())
            ->assertRedirect(route('storefront.cart'));

        $this->assertSame(0, Order::count());
    }

    public function test_payment_proof_is_stored_privately_and_never_on_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $variant = $this->variant();

        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 1]])
            ->post(route('storefront.checkout.store'), $this->payload());

        $order = Order::firstOrFail();

        $this->assertNotEmpty($order->payment_proof_path);
        Storage::disk('local')->assertExists($order->payment_proof_path);
        Storage::disk('public')->assertMissing($order->payment_proof_path);
        // UUID at the root — deliberately not derived from the order id, so the
        // file can be written before the order exists.
        $this->assertStringStartsWith('payment-proofs/', $order->payment_proof_path);
        $this->assertStringNotContainsString("/{$order->id}/", $order->payment_proof_path);
        $this->assertCount(1, Storage::disk('local')->files('payment-proofs'));
    }

    public function test_an_inactive_region_is_rejected(): void
    {
        Storage::fake('local');
        $variant = $this->variant();
        $this->region->update(['is_active' => false]);

        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 1]])
            ->post(route('storefront.checkout.store'), $this->payload())
            ->assertSessionHasErrors('shipping_region_id');

        $this->assertSame(0, Order::count());
    }

    public function test_cart_is_cleared_and_checkout_redirects_to_the_token_url(): void
    {
        Storage::fake('local');
        $variant = $this->variant();

        $response = $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 1]])
            ->post(route('storefront.checkout.store'), $this->payload());

        $order = Order::firstOrFail();

        $response->assertRedirect(route('storefront.confirmation', ['token' => $order->confirmation_token]));
        $this->assertSame([], session(SessionCart::SESSION_KEY, []));
    }
}
