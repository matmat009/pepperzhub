<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The private disk must not be reachable over HTTP.
 *
 * `'serve' => true` on the local disk registers an unauthenticated
 * GET /storage/{path} and PUT /storage/{path} pair straight over that disk —
 * no session, no auth, no CSRF, gated only by a signed URL. It was on.
 *
 * That disk holds customers' payment proofs and nothing else, and nothing in
 * the app ever generated such a URL: every ->url() call targets the `public`
 * disk, and the admin proof route streams these files through its own
 * auth/verified controller action. So the capability was unused in one
 * direction and, in the other, offered arbitrary read *and write* over the most
 * sensitive data here to anyone holding APP_KEY.
 *
 * These assertions are about the route not existing at all, which is stronger
 * than it refusing a particular request — a signature check that stops an
 * unsigned probe still serves anyone who can mint a signature.
 */
class PrivateDiskNotServedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_local_disk_is_not_configured_to_serve_over_http(): void
    {
        $this->assertFalse(
            config('filesystems.disks.local.serve', false),
            'the private disk is being served over HTTP again',
        );
    }

    /** Neither verb's route may exist — not even to be refused. */
    public function test_no_storage_route_is_registered_for_the_private_disk(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('storage.local'));
        $this->assertNull(Route::getRoutes()->getByName('storage.local.upload'));
    }

    public function test_a_guest_cannot_read_a_payment_proof_over_the_storage_route(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payment-proofs/secret.txt', 'BANK RECEIPT');

        $response = $this->get('/storage/payment-proofs/secret.txt');

        $response->assertNotFound();
        $this->assertStringNotContainsString('BANK RECEIPT', $response->getContent());
    }

    public function test_a_guest_cannot_write_to_the_private_disk_over_the_storage_route(): void
    {
        Storage::fake('local');

        // ?upload=true is what the upload handler keys on; without the route it
        // is simply an unrouted URL.
        $this->put('/storage/planted.txt?upload=true', [], ['CONTENT_TYPE' => 'text/plain'])
            ->assertNotFound();

        Storage::disk('local')->assertMissing('planted.txt');
    }

    /**
     * Path traversal, for completeness.
     *
     * Flysystem refuses these on its own, but the route being absent means the
     * attempt never reaches Flysystem in the first place.
     */
    public function test_the_storage_route_cannot_be_used_to_traverse_out_of_the_disk(): void
    {
        foreach (['/storage/../.env', '/storage/..%2f..%2f.env'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    /**
     * The admin route is the supported way in, and it still works.
     *
     * Guarded by this group's auth/verified middleware; covered in full by
     * OrderWorkflowTest. Asserted here too so that removing the storage route
     * can never be mistaken for having removed proof access altogether.
     */
    public function test_the_authenticated_admin_route_remains_the_way_to_read_a_proof(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('admin.orders.payment-proof'));

        $middleware = Route::getRoutes()->getByName('admin.orders.payment-proof')->gatherMiddleware();

        $this->assertContains('auth', $middleware);
        $this->assertContains('verified', $middleware);
    }
}
