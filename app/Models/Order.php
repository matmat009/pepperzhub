<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'confirmation_token',
        'name',
        'social_handle',
        'phone',
        'street',
        'barangay',
        'city',
        'province',
        'zip',
        'notes',
        'shipping_courier_id',
        'shipping_region_id',
        'shipping_courier_name',
        'shipping_region_label',
        'shipping_fee',
        'subtotal',
        'total',
        'payment_method_id',
        'payment_method_name',
        'payment_method_details',
        'payment_proof_path',
        'payment_status',
        'order_status',
        'cancellation_reason',
        'tracking_number',
        'shipped_via',
        'payment_verified_at',
        'processing_at',
        'shipped_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'shipping_fee' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_method_details' => 'array',
            'payment_verified_at' => 'datetime',
            'processing_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * `PZH-` plus the order's own id, zero-padded to five digits.
     *
     * Derived from the id rather than a separate counter, so it cannot collide
     * and needs no locking. TrackOrder's normalizeOrderNo() strips the prefix
     * case-insensitively, so the format is a contract with the frontend.
     */
    public static function referenceFor(int $id): string
    {
        return 'PZH-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    /** Digits only, so "0917 123 4567" and "09171234567" match. */
    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? '';
    }

    /** Mirrors the client's normalizeOrderNo(): drop a leading # and PZH-. */
    public static function normalizeReference(string $reference): string
    {
        $trimmed = ltrim(trim($reference), '#');

        return ltrim(preg_replace('/^PZH-?/i', '', $trimmed) ?? '', '0');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return BelongsTo<ShippingCourier, $this> */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(ShippingCourier::class, 'shipping_courier_id');
    }

    /** @return BelongsTo<ShippingRegion, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(ShippingRegion::class, 'shipping_region_id');
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
