<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingCourier;
use App\Models\ShippingRegion;
use App\Models\User;
use App\Support\SessionCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the admin screens change, the storefront must see.
 *
 * Deliberately storefront-facing: the admin CRUD tests prove a row was written,
 * which is not the same claim as "checkout stopped offering it". is_active is
 * the everyday remove-from-checkout action, so that link is the one worth
 * pinning — and these all go through the real admin routes rather than writing
 * the flag directly, so the whole path is covered.
 */
class CheckoutReferenceDataTest extends TestCase
{
    use RefreshDatabase;

    private PaymentMethod $method;

    private ShippingCourier $courier;

    private ShippingRegion $region;

    protected function setUp(): void
    {
        parent::setUp();

        $this->method = PaymentMethod::create([
            'name' => 'GOtyme Bank',
            'details' => [['label' => 'Bank', 'value' => 'GOtyme Bank']],
            'is_active' => true,
        ]);

        $this->courier = ShippingCourier::create(['name' => 'J&T Express', 'is_active' => true]);

        $this->region = $this->courier->regions()->create([
            'name' => 'Luzon & Visayas',
            'note' => 'Standard pouch',
            'rate' => 150,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    /** Checkout redirects an empty cart, so every case needs a line in it. */
    private function fillCart(): void
    {
        $category = Category::create(['name' => 'Healing', 'slug' => 'healing']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'BPC-157',
            'slug' => 'bpc-157',
            'status' => 'active',
            'short_description' => 'Peptide.',
        ]);

        /** @var ProductVariant $variant */
        $variant = $product->variants()->create([
            'label' => '5mg vial',
            'price' => 2450,
            'stock' => 10,
            'is_kit' => false,
            'sort_order' => 0,
        ]);

        $this->withSession([SessionCart::SESSION_KEY => [$variant->id => 1]]);
    }

    private function asAdmin(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
    }

    /**
     * @return array<string, mixed>
     */
    private function courierPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => $this->courier->name,
            'is_active' => true,
            'sort_order' => 0,
            'regions' => [[
                'id' => $this->region->id,
                'name' => $this->region->name,
                'note' => $this->region->note,
                'rate' => (float) $this->region->rate,
                'is_active' => true,
            ]],
        ], $overrides);
    }

    public function test_checkout_offers_active_reference_data(): void
    {
        $this->fillCart();

        $this->get(route('storefront.checkout'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('paymentMethods', 1)
                ->where('paymentMethods.0.name', 'GOtyme Bank')
                ->has('couriers', 1)
                ->has('couriers.0.regions', 1)
            );
    }

    public function test_deactivating_a_payment_method_removes_it_from_checkout(): void
    {
        $this->asAdmin();

        $this->put(route('admin.payment-methods.update', $this->method), [
            'name' => 'GOtyme Bank',
            'details' => [['label' => 'Bank', 'value' => 'GOtyme Bank']],
            'is_active' => false,
            'sort_order' => 0,
        ])->assertRedirect();

        $this->fillCart();

        $this->get(route('storefront.checkout'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('paymentMethods', 0));

        // Still on file, not deleted — that is the point of the flag.
        $this->assertSame(1, PaymentMethod::count());
    }

    public function test_deactivating_a_courier_removes_it_from_checkout(): void
    {
        $this->asAdmin();

        $this->put(
            route('admin.shipping-couriers.update', $this->courier),
            $this->courierPayload(['is_active' => false]),
        )->assertRedirect();

        $this->fillCart();

        $this->get(route('storefront.checkout'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('couriers', 0));

        $this->assertSame(1, ShippingCourier::count());
    }

    /** A courier can stay live while one of its regions is withdrawn. */
    public function test_deactivating_a_region_removes_only_that_option(): void
    {
        $this->asAdmin();

        $second = $this->courier->regions()->create([
            'name' => 'Mindanao (Small)',
            'rate' => 100,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $payload = $this->courierPayload();
        $payload['regions'][] = [
            'id' => $second->id,
            'name' => $second->name,
            'note' => null,
            'rate' => 100,
            'is_active' => false,
        ];

        $this->put(route('admin.shipping-couriers.update', $this->courier), $payload)
            ->assertRedirect();

        $this->fillCart();

        $this->get(route('storefront.checkout'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('couriers', 1)
                ->has('couriers.0.regions', 1)
                ->where('couriers.0.regions.0.name', 'Luzon & Visayas')
            );
    }

    /** Reactivating is the same switch in reverse; it must come straight back. */
    public function test_reactivating_a_payment_method_returns_it_to_checkout(): void
    {
        $this->asAdmin();

        $payload = [
            'name' => 'GOtyme Bank',
            'details' => [['label' => 'Bank', 'value' => 'GOtyme Bank']],
            'sort_order' => 0,
        ];

        $this->put(route('admin.payment-methods.update', $this->method), $payload + ['is_active' => false]);
        $this->put(route('admin.payment-methods.update', $this->method), $payload + ['is_active' => true]);

        $this->fillCart();

        $this->get(route('storefront.checkout'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('paymentMethods', 1));
    }

    /** An edited rate is what the next checkout quotes — no redeploy needed. */
    public function test_an_edited_rate_is_what_checkout_shows(): void
    {
        $this->asAdmin();

        $payload = $this->courierPayload();
        // A decimal rate on purpose: it proves the decimal:2 cast survives the
        // round trip, and avoids 175.0 arriving as the integer 175 in JSON.
        $payload['regions'][0]['rate'] = 175.5;

        $this->put(route('admin.shipping-couriers.update', $this->courier), $payload)
            ->assertRedirect();

        $this->fillCart();

        $this->get(route('storefront.checkout'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('couriers.0.regions.0.rate', 175.5));
    }
}
