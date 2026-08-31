<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\ShippingCourier;
use App\Models\ShippingRegion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShippingCourierCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'J&T Express',
            'is_active' => true,
            'sort_order' => 0,
            'regions' => [
                ['id' => null, 'name' => 'Luzon & Visayas', 'note' => 'Standard pouch', 'rate' => 150, 'is_active' => true],
                ['id' => null, 'name' => 'Mindanao (Small)', 'note' => 'Max. 2 kits', 'rate' => 100, 'is_active' => true],
            ],
        ], $overrides);
    }

    private function courierWithRegions(): ShippingCourier
    {
        $this->post(route('admin.shipping-couriers.store'), $this->payload());

        return ShippingCourier::with('regions')->sole();
    }

    /**
     * @param  array<int, array<string, mixed>>  $regions
     * @return array<int, array<string, mixed>>
     */
    private function rowsFrom(ShippingCourier $courier, array $regions = []): array
    {
        return $regions !== [] ? $regions : $courier->regions
            ->map(fn (ShippingRegion $region) => [
                'id' => $region->id,
                'name' => $region->name,
                'note' => $region->note,
                'rate' => (float) $region->rate,
                'is_active' => (bool) $region->is_active,
            ])
            ->all();
    }

    // ----- create -----------------------------------------------------------

    public function test_a_courier_is_created_with_its_regions(): void
    {
        $this->post(route('admin.shipping-couriers.store'), $this->payload())
            ->assertRedirect();

        $courier = ShippingCourier::with('regions')->sole();

        $this->assertSame('J&T Express', $courier->name);
        $this->assertTrue($courier->is_active);
        $this->assertCount(2, $courier->regions);
        $this->assertSame('Luzon & Visayas', $courier->regions[0]->name);
        $this->assertSame('150.00', $courier->regions[0]->rate);
        // sort_order follows form order, matching how variants are sequenced.
        $this->assertSame(0, $courier->regions[0]->sort_order);
        $this->assertSame(1, $courier->regions[1]->sort_order);
    }

    public function test_a_region_needs_a_name_and_rate(): void
    {
        $this->post(route('admin.shipping-couriers.store'), $this->payload([
            'regions' => [['id' => null, 'name' => '', 'note' => 'x', 'rate' => 150, 'is_active' => true]],
        ]))->assertSessionHasErrors('regions.0.name');

        $this->post(route('admin.shipping-couriers.store'), $this->payload([
            'regions' => [['id' => null, 'name' => 'Luzon', 'note' => null, 'rate' => -1, 'is_active' => true]],
        ]))->assertSessionHasErrors('regions.0.rate');
    }

    public function test_a_courier_can_be_created_without_regions(): void
    {
        // A blank starter row must not block the save.
        $this->post(route('admin.shipping-couriers.store'), $this->payload([
            'regions' => [['id' => null, 'name' => '', 'note' => '', 'rate' => '', 'is_active' => true]],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertCount(0, ShippingCourier::sole()->regions);
    }

    // ----- id-preserving upsert ---------------------------------------------

    /**
     * The load-bearing test for this screen.
     *
     * orders.shipping_region_id points at these rows. A delete-all-reinsert
     * save would reissue ids and silently repoint historical orders at whichever
     * region landed on the old number — the same bug class already fixed once
     * for order_items.product_variant_id.
     */
    public function test_editing_a_rate_leaves_every_region_id_untouched(): void
    {
        $courier = $this->courierWithRegions();
        $before = $courier->regions->pluck('id')->all();

        $rows = $this->rowsFrom($courier);
        $rows[0]['rate'] = 175;

        $this->put(route('admin.shipping-couriers.update', $courier), $this->payload([
            'regions' => $rows,
        ]))->assertRedirect();

        $after = $courier->fresh()->regions;

        $this->assertSame($before, $after->pluck('id')->all(), 'region ids were reissued');
        $this->assertSame('175.00', $after[0]->rate);
    }

    public function test_removing_one_region_deletes_only_that_row(): void
    {
        $courier = $this->courierWithRegions();
        [$kept, $dropped] = [$courier->regions[0], $courier->regions[1]];

        $this->put(route('admin.shipping-couriers.update', $courier), $this->payload([
            'regions' => [$this->rowsFrom($courier)[0]],
        ]))->assertRedirect();

        $this->assertDatabaseHas('shipping_regions', ['id' => $kept->id]);
        $this->assertDatabaseMissing('shipping_regions', ['id' => $dropped->id]);
    }

    public function test_adding_a_region_does_not_disturb_the_others(): void
    {
        $courier = $this->courierWithRegions();
        $before = $courier->regions->pluck('id')->all();

        $rows = $this->rowsFrom($courier);
        $rows[] = ['id' => null, 'name' => 'Mindanao (Large Pouch)', 'note' => 'Min. 5 kits', 'rate' => 200, 'is_active' => true];

        $this->put(route('admin.shipping-couriers.update', $courier), $this->payload([
            'regions' => $rows,
        ]))->assertRedirect();

        $after = $courier->fresh()->regions;

        $this->assertCount(3, $after);
        $this->assertSame($before, $after->take(2)->pluck('id')->all());
    }

    /** An id from another courier must be inserted as new, never hijacked. */
    public function test_a_region_id_belonging_to_another_courier_is_inserted_rather_than_hijacked(): void
    {
        $mine = $this->courierWithRegions();

        $theirs = ShippingCourier::create(['name' => 'LBC Express', 'is_active' => true]);
        /** @var ShippingRegion $foreign */
        $foreign = $theirs->regions()->create([
            'name' => 'Nationwide',
            'rate' => 220,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $rows = $this->rowsFrom($mine);
        $rows[] = ['id' => $foreign->id, 'name' => 'Stolen', 'note' => null, 'rate' => 999, 'is_active' => true];

        $this->put(route('admin.shipping-couriers.update', $mine), $this->payload([
            'regions' => $rows,
        ]))->assertRedirect();

        $foreign->refresh();

        $this->assertSame('Nationwide', $foreign->name, "another courier's region was overwritten");
        $this->assertSame('220.00', $foreign->rate);
        $this->assertSame($theirs->id, $foreign->shipping_courier_id);
        $this->assertCount(3, $mine->fresh()->regions);
    }

    // ----- update / delete --------------------------------------------------

    public function test_a_courier_can_be_updated_and_deactivated(): void
    {
        $courier = $this->courierWithRegions();

        $this->put(route('admin.shipping-couriers.update', $courier), $this->payload([
            'name' => 'J&T Express PH',
            'is_active' => false,
            'sort_order' => 2,
            'regions' => $this->rowsFrom($courier),
        ]))->assertRedirect();

        $courier->refresh();

        $this->assertSame('J&T Express PH', $courier->name);
        $this->assertFalse($courier->is_active);
        $this->assertSame(2, $courier->sort_order);
    }

    /**
     * Cascade verified against the live schema rather than assumed:
     * shipping_regions.shipping_courier_id is constrained cascadeOnDelete.
     */
    public function test_deleting_a_courier_removes_its_regions(): void
    {
        $courier = $this->courierWithRegions();
        $regionIds = $courier->regions->pluck('id')->all();

        $this->delete(route('admin.shipping-couriers.destroy', $courier))->assertRedirect();

        $this->assertSame(0, ShippingCourier::count());
        foreach ($regionIds as $id) {
            $this->assertDatabaseMissing('shipping_regions', ['id' => $id]);
        }
    }

    public function test_a_region_can_be_removed_independently(): void
    {
        $courier = $this->courierWithRegions();
        $region = $courier->regions[1];

        $this->delete(route('admin.shipping-couriers.regions.destroy', [$courier, $region->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('shipping_regions', ['id' => $region->id]);
        $this->assertCount(1, $courier->fresh()->regions);
        $this->assertSame(1, ShippingCourier::count());
    }

    /** Scoped through the relation, so one courier cannot delete another's row. */
    public function test_a_region_cannot_be_removed_through_a_different_courier(): void
    {
        $mine = $this->courierWithRegions();

        $theirs = ShippingCourier::create(['name' => 'LBC Express', 'is_active' => true]);
        $foreign = $theirs->regions()->create([
            'name' => 'Nationwide',
            'rate' => 220,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->delete(route('admin.shipping-couriers.regions.destroy', [$mine, $foreign->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('shipping_regions', ['id' => $foreign->id]);
    }

    public function test_the_index_lists_inactive_couriers_too(): void
    {
        ShippingCourier::create(['name' => 'Live', 'is_active' => true]);
        ShippingCourier::create(['name' => 'Retired', 'is_active' => false]);

        $this->get(route('admin.shipping-couriers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('couriers', 2));
    }

    public function test_admin_courier_routes_require_authentication(): void
    {
        $courier = $this->courierWithRegions();

        auth()->logout();

        $this->get(route('admin.shipping-couriers.index'))->assertRedirect(route('login'));
        $this->post(route('admin.shipping-couriers.store'), $this->payload())->assertRedirect(route('login'));
        $this->put(route('admin.shipping-couriers.update', $courier), $this->payload())->assertRedirect(route('login'));
        $this->delete(route('admin.shipping-couriers.destroy', $courier))->assertRedirect(route('login'));
    }

    // ----- historical orders are unaffected ---------------------------------

    public function test_deleting_a_courier_leaves_historical_orders_intact(): void
    {
        $courier = $this->courierWithRegions();
        $order = $this->orderUsing($courier);

        $this->delete(route('admin.shipping-couriers.destroy', $courier))->assertRedirect();

        $order->refresh();

        $this->assertNull($order->shipping_courier_id, 'the courier FK should have been nulled');
        $this->assertNull($order->shipping_region_id, 'the region FK should have been nulled');
        $this->assertSame('J&T Express', $order->shipping_courier_name);
        $this->assertSame('Luzon & Visayas', $order->shipping_region_label);

        // Confirmation still renders the snapshot.
        $this->get(route('storefront.confirmation', ['token' => $order->confirmation_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('order.courier', 'J&T Express'));

        // So does Track Order.
        $this->post(route('storefront.track.lookup'), [
            'order_number' => $order->order_number,
            'phone' => '0917 123 4567',
        ])->assertInertia(fn ($page) => $page->where('result.courier', 'J&T Express'));
    }

    private function orderUsing(ShippingCourier $courier): Order
    {
        $method = PaymentMethod::create([
            'name' => 'GOtyme Bank',
            'details' => [['label' => 'Bank', 'value' => 'GOtyme Bank']],
            'is_active' => true,
        ]);

        $region = $courier->regions->first();

        $order = Order::create([
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
            'shipping_courier_name' => 'J&T Express',
            'shipping_region_label' => 'Luzon & Visayas',
            'shipping_fee' => 150,
            'subtotal' => 2450,
            'total' => 2600,
            'payment_method_id' => $method->id,
            'payment_method_name' => 'GOtyme Bank',
            'payment_method_details' => [['label' => 'Bank', 'value' => 'GOtyme Bank']],
            'payment_proof_path' => 'payment-proofs/x.jpg',
        ]);

        $order->forceFill(['order_number' => Order::referenceFor($order->id)])->save();

        return $order->refresh();
    }
}
