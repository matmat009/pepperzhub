<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\ShippingCourier;
use App\Models\ShippingRegion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentMethodCrudTest extends TestCase
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
            'name' => 'GOtyme Bank',
            'details' => [
                ['label' => 'Bank', 'value' => 'GOtyme Bank'],
                ['label' => 'Account Number', 'value' => '0012 3456 7890'],
            ],
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides);
    }

    // ----- create -----------------------------------------------------------

    public function test_a_payment_method_can_be_created(): void
    {
        $this->post(route('admin.payment-methods.store'), $this->payload())
            ->assertRedirect();

        $method = PaymentMethod::sole();

        $this->assertSame('GOtyme Bank', $method->name);
        $this->assertTrue($method->is_active);
        $this->assertSame(
            [
                ['label' => 'Bank', 'value' => 'GOtyme Bank'],
                ['label' => 'Account Number', 'value' => '0012 3456 7890'],
            ],
            $method->details,
        );
    }

    public function test_a_method_needs_at_least_one_detail(): void
    {
        // The repeater always renders one row, so a blank pair is what an
        // untouched form actually submits — it must be rejected, not saved.
        $this->post(route('admin.payment-methods.store'), $this->payload([
            'details' => [['label' => '', 'value' => '']],
        ]))->assertSessionHasErrors('details');

        $this->assertSame(0, PaymentMethod::count());
    }

    public function test_a_half_filled_detail_row_is_rejected(): void
    {
        $this->post(route('admin.payment-methods.store'), $this->payload([
            'details' => [['label' => 'Account Number', 'value' => '']],
        ]))->assertSessionHasErrors('details.0.value');
    }

    // ----- update -----------------------------------------------------------

    public function test_a_payment_method_can_be_updated(): void
    {
        $method = PaymentMethod::create($this->payload());

        $this->put(route('admin.payment-methods.update', $method), $this->payload([
            'name' => 'GCash',
            'details' => [['label' => 'Mobile Number', 'value' => '0917 000 1111']],
            'sort_order' => 3,
        ]))->assertRedirect();

        $method->refresh();

        $this->assertSame('GCash', $method->name);
        $this->assertSame(3, $method->sort_order);
        $this->assertSame(
            [['label' => 'Mobile Number', 'value' => '0917 000 1111']],
            $method->details,
        );
    }

    public function test_a_payment_method_can_be_deactivated(): void
    {
        $method = PaymentMethod::create($this->payload());

        $this->put(route('admin.payment-methods.update', $method), $this->payload([
            'is_active' => false,
        ]))->assertRedirect();

        $this->assertFalse($method->fresh()->is_active);
    }

    // ----- QR code ----------------------------------------------------------

    public function test_a_qr_code_is_stored_on_the_public_disk(): void
    {
        Storage::fake('public');

        $this->post(route('admin.payment-methods.store'), $this->payload([
            'qr_code' => UploadedFile::fake()->image('qr.png'),
        ]))->assertRedirect();

        $path = PaymentMethod::sole()->qr_code_path;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_a_qr_code_deletes_the_previous_file(): void
    {
        Storage::fake('public');

        $this->post(route('admin.payment-methods.store'), $this->payload([
            'qr_code' => UploadedFile::fake()->image('first.png'),
        ]));

        $method = PaymentMethod::sole();
        $first = $method->qr_code_path;

        $this->put(route('admin.payment-methods.update', $method), $this->payload([
            'qr_code' => UploadedFile::fake()->image('second.png'),
        ]));

        $second = $method->fresh()->qr_code_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_a_qr_code_can_be_removed(): void
    {
        Storage::fake('public');

        $this->post(route('admin.payment-methods.store'), $this->payload([
            'qr_code' => UploadedFile::fake()->image('qr.png'),
        ]));

        $method = PaymentMethod::sole();
        $path = $method->qr_code_path;

        $this->put(route('admin.payment-methods.update', $method), $this->payload([
            'remove_qr_code' => true,
        ]));

        $this->assertNull($method->fresh()->qr_code_path);
        Storage::disk('public')->assertMissing($path);
    }

    /** An untouched form must not disturb the stored QR code. */
    public function test_saving_without_a_file_keeps_the_existing_qr_code(): void
    {
        Storage::fake('public');

        $this->post(route('admin.payment-methods.store'), $this->payload([
            'qr_code' => UploadedFile::fake()->image('qr.png'),
        ]));

        $method = PaymentMethod::sole();
        $path = $method->qr_code_path;

        $this->put(route('admin.payment-methods.update', $method), $this->payload([
            'name' => 'Renamed',
        ]));

        $this->assertSame($path, $method->fresh()->qr_code_path);
        Storage::disk('public')->assertExists($path);
    }

    // ----- delete -----------------------------------------------------------

    /**
     * No referential guard, unlike Category.
     *
     * orders.payment_method_id is nullOnDelete and the order snapshots the name
     * and details it displays, so deletion cannot corrupt order history.
     */
    public function test_a_payment_method_can_be_deleted(): void
    {
        Storage::fake('public');

        $this->post(route('admin.payment-methods.store'), $this->payload([
            'qr_code' => UploadedFile::fake()->image('qr.png'),
        ]));

        $method = PaymentMethod::sole();
        $path = $method->qr_code_path;

        $this->delete(route('admin.payment-methods.destroy', $method))->assertRedirect();

        $this->assertSame(0, PaymentMethod::count());
        // The QR code is a real file on the public disk; deleting the row must
        // not leave it orphaned.
        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_index_lists_inactive_methods_too(): void
    {
        PaymentMethod::create($this->payload(['name' => 'Live']));
        PaymentMethod::create($this->payload(['name' => 'Retired', 'is_active' => false]));

        // Inactive rows have to appear here — this screen is where they are
        // reactivated.
        $this->get(route('admin.payment-methods.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('paymentMethods', 2));
    }

    public function test_admin_payment_method_routes_require_authentication(): void
    {
        $method = PaymentMethod::create($this->payload());

        auth()->logout();

        $this->get(route('admin.payment-methods.index'))->assertRedirect(route('login'));
        $this->post(route('admin.payment-methods.store'), $this->payload())->assertRedirect(route('login'));
        $this->put(route('admin.payment-methods.update', $method), $this->payload())->assertRedirect(route('login'));
        $this->delete(route('admin.payment-methods.destroy', $method))->assertRedirect(route('login'));
    }

    // ----- historical orders are unaffected ---------------------------------

    /**
     * Deletion through the real admin route, not a raw DB delete.
     *
     * The Phase 1.1 snapshot tests already cover the constraint itself; this
     * confirms the same holds through the UI path this phase adds.
     */
    public function test_deleting_a_method_leaves_historical_orders_intact(): void
    {
        $method = PaymentMethod::create($this->payload());
        $order = $this->orderUsing($method);

        $this->delete(route('admin.payment-methods.destroy', $method))->assertRedirect();

        $order->refresh();

        $this->assertNull($order->payment_method_id, 'the FK should have been nulled');
        $this->assertSame('GOtyme Bank', $order->payment_method_name);
        $this->assertSame(
            [['label' => 'Bank', 'value' => 'GOtyme Bank']],
            $order->payment_method_details,
        );

        // What the customer sees, not just what the column holds.
        $this->get(route('storefront.confirmation', ['token' => $order->confirmation_token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('order.payment_method', 'GOtyme Bank'));

        // And what the admin sees.
        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('order.payment_method_name', 'GOtyme Bank'));
    }

    private function orderUsing(PaymentMethod $method): Order
    {
        $courier = ShippingCourier::create(['name' => 'J&T Express', 'is_active' => true]);
        /** @var ShippingRegion $region */
        $region = $courier->regions()->create([
            'name' => 'Luzon & Visayas',
            'rate' => 150,
            'is_active' => true,
        ]);

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
