<?php

namespace Tests\Feature\Storefront;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\ShippingCourier;
use App\Models\ShippingRegion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrackOrderTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $overrides = []): Order
    {
        $method = PaymentMethod::create(['name' => 'GOtyme Bank', 'is_active' => true]);
        $courier = ShippingCourier::create(['name' => 'J&T Express', 'is_active' => true]);
        /** @var ShippingRegion $region */
        $region = $courier->regions()->create([
            'name' => 'Luzon & Visayas',
            'rate' => 150,
            'is_active' => true,
        ]);

        $order = Order::create(array_merge([
            'confirmation_token' => Str::random(40),
            'name' => 'Juan Dela Cruz',
            'social_handle' => 'fb.com/juandc',
            'phone' => '0917 123 4567',
            'street' => '12 Mabini St',
            'barangay' => 'San Antonio',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'zip' => '1203',
            'shipping_courier_id' => $courier->id,
            'shipping_region_id' => $region->id,
            'shipping_region_label' => 'Luzon & Visayas',
            'shipping_fee' => 150,
            'subtotal' => 2450,
            'total' => 2600,
            'payment_method_id' => $method->id,
            'payment_proof_path' => 'payment-proofs/1/x.jpg',
        ], $overrides));

        $order->forceFill(['order_number' => Order::referenceFor($order->id)])->save();

        return $order->refresh();
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('');
        parent::tearDown();
    }

    public function test_a_correct_pair_returns_the_order(): void
    {
        $order = $this->order();

        $this->post(route('storefront.track.lookup'), [
            'order_number' => $order->order_number,
            'phone' => '0917 123 4567',
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notFound', false)
                ->where('result.order_number', $order->order_number)
            );
    }

    public function test_the_prefix_and_padding_are_optional(): void
    {
        $order = $this->order();

        foreach ([$order->order_number, strtolower($order->order_number), (string) $order->id, '#'.$order->order_number] as $reference) {
            $this->post(route('storefront.track.lookup'), [
                'order_number' => $reference,
                'phone' => '09171234567',
            ])->assertInertia(fn ($page) => $page->where('notFound', false));
        }
    }

    public function test_a_wrong_phone_finds_nothing(): void
    {
        $order = $this->order();

        $this->post(route('storefront.track.lookup'), [
            'order_number' => $order->order_number,
            'phone' => '09990000000',
        ])->assertInertia(fn ($page) => $page->where('notFound', true)->where('result', null));
    }

    public function test_an_unknown_order_number_finds_nothing(): void
    {
        $this->order();

        $this->post(route('storefront.track.lookup'), [
            'order_number' => 'PZH-99999',
            'phone' => '0917 123 4567',
        ])->assertInertia(fn ($page) => $page->where('notFound', true)->where('result', null));
    }

    /**
     * Both failures must be indistinguishable, or the endpoint becomes a way to
     * enumerate which order numbers exist.
     */
    public function test_both_failure_modes_return_the_same_shape(): void
    {
        $order = $this->order();

        $wrongPhone = $this->post(route('storefront.track.lookup'), [
            'order_number' => $order->order_number,
            'phone' => '09990000000',
        ]);

        $unknownOrder = $this->post(route('storefront.track.lookup'), [
            'order_number' => 'PZH-99999',
            'phone' => '0917 123 4567',
        ]);

        $this->assertSame(
            $wrongPhone->inertiaProps()['notFound'] ?? null,
            $unknownOrder->inertiaProps()['notFound'] ?? null,
        );
        $this->assertSame(
            $wrongPhone->inertiaProps()['result'] ?? 'missing',
            $unknownOrder->inertiaProps()['result'] ?? 'missing',
        );
    }

    public function test_lookup_is_rate_limited(): void
    {
        $this->order();

        // The route allows 6 a minute; the seventh must be refused.
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post(route('storefront.track.lookup'), [
                'order_number' => 'PZH-99999',
                'phone' => '0000',
            ])->assertStatus(200);
        }

        $this->post(route('storefront.track.lookup'), [
            'order_number' => 'PZH-99999',
            'phone' => '0000',
        ])->assertStatus(429);
    }

    public function test_the_tracker_stage_is_derived_from_both_status_fields(): void
    {
        $cases = [
            ['unverified', 'pending', 0],
            ['verified', 'pending', 1],
            ['verified', 'processing', 2],
            ['verified', 'shipped', 3],
            ['verified', 'completed', 4],
        ];

        foreach ($cases as [$payment, $status, $expected]) {
            $order = $this->order(['payment_status' => $payment, 'order_status' => $status]);

            $this->post(route('storefront.track.lookup'), [
                'order_number' => $order->order_number,
                'phone' => '0917 123 4567',
            ])->assertInertia(fn ($page) => $page->where('result.tracker.stage', $expected));

            RateLimiter::clear('');
            $order->delete();
        }
    }

    // ----- courier display --------------------------------------------------

    /** The courier string the page would render for this order. */
    private function displayedCourier(Order $order): ?string
    {
        $response = $this->post(route('storefront.track.lookup'), [
            'order_number' => $order->order_number,
            'phone' => '0917 123 4567',
        ]);

        $response->assertInertia(fn ($page) => $page->where('notFound', false));

        return $response->inertiaProps()['result']['courier'] ?? null;
    }

    public function test_the_courier_shown_is_the_snapshot_before_shipping(): void
    {
        $order = $this->order(['shipping_courier_name' => 'J&T Express']);

        $this->assertSame('J&T Express', $this->displayedCourier($order));
    }

    public function test_the_courier_shown_is_shipped_via_once_shipped(): void
    {
        // What actually carried the parcel wins over the checkout-time quote.
        $order = $this->order([
            'payment_status' => 'verified',
            'order_status' => 'shipped',
            'shipping_courier_name' => 'J&T Express',
            'shipped_via' => 'LBC Express',
        ]);

        $this->assertSame('LBC Express', $this->displayedCourier($order));
    }

    /**
     * The FK is nullOnDelete, so reading the live relation would blank the
     * courier on every historical order the day one is retired.
     */
    public function test_deleting_the_courier_does_not_change_the_displayed_courier(): void
    {
        $order = $this->order(['shipping_courier_name' => 'J&T Express']);

        ShippingCourier::query()->delete();

        $order->refresh();
        $this->assertNull($order->shipping_courier_id, 'the FK should have been nulled');
        $this->assertSame('J&T Express', $this->displayedCourier($order));
    }

    public function test_renaming_the_courier_does_not_change_the_displayed_courier(): void
    {
        $order = $this->order(['shipping_courier_name' => 'J&T Express']);

        ShippingCourier::query()->update(['name' => 'J&T Express (PH)']);

        // The order still reads as it did on the day it was placed.
        $this->assertSame('J&T Express', $this->displayedCourier($order));
    }
}
