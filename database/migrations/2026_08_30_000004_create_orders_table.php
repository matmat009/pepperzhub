<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Derived from the id straight after insert (PZH- + 5 digits), so
            // it cannot be known until the row exists.
            $table->string('order_number')->nullable()->unique();

            $table->string('name');
            $table->string('social_handle');
            $table->string('phone');

            $table->string('street');
            $table->string('barangay');
            $table->string('city');
            $table->string('province');
            $table->string('zip');
            $table->text('notes')->nullable();

            // Restricted, not cascading: an order must survive the courier or
            // payment method being tidied up later.
            $table->foreignId('shipping_courier_id')->constrained()->restrictOnDelete();
            $table->foreignId('shipping_region_id')->constrained()->restrictOnDelete();
            // Snapshot, because a region can be renamed or re-priced after the
            // fact and the order must still read as it did on the day.
            $table->string('shipping_region_label');
            $table->decimal('shipping_fee', 10, 2)->default(0);

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            // Private disk only — never public, never symlinked.
            $table->string('payment_proof_path');

            // Two independent axes, deliberately not one enum: payment can be
            // rejected while the order is still pending, and the customer-facing
            // tracker is derived from the pair rather than stored.
            $table->enum('payment_status', ['unverified', 'verified', 'rejected'])->default('unverified');
            $table->enum('order_status', ['pending', 'processing', 'shipped', 'completed', 'cancelled'])->default('pending');

            $table->string('tracking_number')->nullable();
            $table->timestamps();

            $table->index(['payment_status', 'order_status']);
            // Track Order looks up on both fields together.
            $table->index(['order_number', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
