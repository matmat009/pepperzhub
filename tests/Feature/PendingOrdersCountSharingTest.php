<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Support\Header;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PendingOrdersCountSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_storefront_responses_omit_the_pending_order_count(): void
    {
        $this->get(route('storefront.faq'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Faq')
                ->missing('pendingOrdersCount')
            );
    }

    public function test_authenticated_admin_storefront_responses_omit_the_pending_order_count(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('storefront.faq'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Faq')
                ->missing('pendingOrdersCount')
            );
    }

    public function test_authorized_admin_responses_retain_the_correct_pending_order_count(): void
    {
        $this->order();
        $this->order();
        $this->order(['payment_status' => 'verified']);
        $this->order(['order_status' => 'processing']);

        $this->actingAs(User::factory()->create());

        $this->get(route('admin.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/products/all-products/Index')
                ->where('pendingOrdersCount', 2)
            );

        $this->withHeaders([
            Header::PARTIAL_COMPONENT => 'admin/products/all-products/Index',
            Header::PARTIAL_ONLY => 'pendingOrdersCount',
        ])->get(route('admin.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/products/all-products/Index')
                ->where('pendingOrdersCount', 2)
            );
    }

    public function test_storefront_partial_reload_cannot_request_the_admin_count(): void
    {
        $this->actingAs(User::factory()->create());

        $this->withHeaders([
            Header::PARTIAL_COMPONENT => 'storefront/Faq',
            Header::PARTIAL_ONLY => 'pendingOrdersCount',
        ])->get(route('storefront.faq'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('storefront/Faq')
                ->missing('pendingOrdersCount')
            );
    }

    public function test_unauthorized_admin_partial_reload_cannot_retrieve_the_count(): void
    {
        $headers = [
            Header::PARTIAL_COMPONENT => 'admin/products/all-products/Index',
            Header::PARTIAL_ONLY => 'pendingOrdersCount',
        ];

        $this->withHeaders($headers)
            ->get(route('admin.products.index'))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create());

        $this->withHeaders($headers)
            ->get(route('admin.products.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_public_storefront_responses_do_not_query_the_pending_order_count(): void
    {
        $orderQueries = [];

        DB::listen(function (QueryExecuted $query) use (&$orderQueries): void {
            if (preg_match('/\\bfrom\\s+["`]?orders["`]?/i', $query->sql) === 1) {
                $orderQueries[] = $query->sql;
            }
        });

        $this->get(route('storefront.faq'))->assertOk();

        $this->withHeaders([
            Header::PARTIAL_COMPONENT => 'storefront/Faq',
            Header::PARTIAL_ONLY => 'pendingOrdersCount',
        ])->get(route('storefront.faq'))->assertOk();

        $this->assertSame([], $orderQueries);
    }

    /** @param array<string, string> $overrides */
    private function order(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'confirmation_token' => Str::random(40),
            'name' => 'Juan Dela Cruz',
            'social_handle' => 'fb.com/juandc',
            'phone' => '0917 123 4567',
            'street' => '12 Mabini St',
            'barangay' => 'San Antonio',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'zip' => '1203',
            'shipping_region_label' => 'Luzon & Visayas',
            'shipping_fee' => 150,
            'subtotal' => 1000,
            'total' => 1150,
            'payment_proof_path' => 'payment-proofs/proof.jpg',
            'payment_status' => 'unverified',
            'order_status' => 'pending',
        ], $overrides));
    }
}
