<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for the methods Checkout offers.
 *
 * Deliberately without the referential guard CategoryController carries. That
 * guard exists because products.category_id is restricted on delete; here
 * orders.payment_method_id is nullOnDelete and every order snapshots
 * payment_method_name and payment_method_details at creation time, so deleting
 * a method can never blank or corrupt a historical order's display. The
 * protection is structurally unnecessary rather than merely unimplemented.
 *
 * Day to day the intended action is is_active, not deletion: Checkout filters
 * to active rows, so toggling it off stops new orders selecting the method
 * while leaving it on file.
 */
class PaymentMethodController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    private function toRow(PaymentMethod $method): array
    {
        return [
            'id' => $method->id,
            'name' => $method->name,
            'details' => $method->details ?? [],
            // A URL, never the raw path — the client has no use for where on
            // disk this sits, matching how order payment proofs are handled.
            'qr_code_url' => $method->qr_code_path
                ? Storage::disk('public')->url($method->qr_code_path)
                : null,
            'is_active' => (bool) $method->is_active,
            'sort_order' => (int) $method->sort_order,
            'order_count' => (int) $method->orders_count,
        ];
    }

    public function index(): Response
    {
        // Inactive rows included: this screen is where they are reactivated.
        $methods = PaymentMethod::query()
            ->withCount('orders')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PaymentMethod $method) => $this->toRow($method))
            ->all();

        return Inertia::render('admin/payment-methods/Index', [
            'paymentMethods' => $methods,
        ]);
    }

    public function store(PaymentMethodRequest $request): RedirectResponse
    {
        $method = PaymentMethod::create($this->attributes($request));

        $this->syncQrCode($method, $request);

        $this->toast('Payment method created.');

        return back();
    }

    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->update($this->attributes($request));

        $this->syncQrCode($paymentMethod, $request);

        $this->toast('Payment method updated.');

        return back();
    }

    /**
     * Hard delete. See the class docblock for why no referential guard applies.
     */
    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        if ($paymentMethod->qr_code_path) {
            Storage::disk('public')->delete($paymentMethod->qr_code_path);
        }

        $paymentMethod->delete();

        $this->toast('Payment method deleted. Existing orders keep their own copy of these details.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(PaymentMethodRequest $request): array
    {
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
            'details' => array_map(
                fn (array $row): array => [
                    'label' => $row['label'],
                    'value' => $row['value'],
                ],
                $validated['details'],
            ),
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'],
        ];
    }

    /**
     * Replace, remove, or leave the QR code alone.
     *
     * Same shape as ProductController::syncImages: the old file is deleted from
     * the public disk whenever it stops being referenced, so replacing a QR
     * code does not leave the previous one orphaned.
     */
    private function syncQrCode(PaymentMethod $method, PaymentMethodRequest $request): void
    {
        $file = $request->file('qr_code');
        $removing = $request->boolean('remove_qr_code');

        if (! $file && ! $removing) {
            return;
        }

        if ($method->qr_code_path) {
            Storage::disk('public')->delete($method->qr_code_path);
        }

        $method->update([
            'qr_code_path' => $file ? $file->store('payment-methods', 'public') : null,
        ]);
    }
}
