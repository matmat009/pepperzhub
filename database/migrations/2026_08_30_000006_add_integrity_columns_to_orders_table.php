<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Phase 1.1 integrity patch. A new migration rather than an edit to
 * 2026_08_30_000004, which has already run.
 *
 * Three things:
 *   - confirmation_token, so the confirmation page survives a refresh instead
 *     of depending on a one-shot session flash.
 *   - Snapshots of the payment method and courier, matching what
 *     shipping_region_label already does — an order must still read correctly
 *     after the reference row it points at is renamed or retired.
 *   - The reference FKs move from restrictOnDelete to nullOnDelete. Restrict
 *     made historical orders block the deletion of a courier; with the
 *     snapshots above, nulling the live link costs the order nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Added nullable first so existing rows can be backfilled before
            // the not-null and unique constraints go on.
            $table->string('confirmation_token', 64)->nullable()->after('order_number');

            $table->string('payment_method_name')->nullable()->after('payment_method_id');
            $table->json('payment_method_details')->nullable()->after('payment_method_name');
            $table->string('shipping_courier_name')->nullable()->after('shipping_courier_id');

            $table->string('cancellation_reason')->nullable()->after('order_status');
        });

        DB::table('orders')->whereNull('confirmation_token')->orderBy('id')->each(function ($order) {
            DB::table('orders')
                ->where('id', $order->id)
                ->update(['confirmation_token' => Str::random(40)]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('confirmation_token', 64)->nullable(false)->change();
            $table->unique('confirmation_token');
        });

        // Reference links become nullable so a courier or payment method can be
        // deleted without taking order history with it.
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['shipping_courier_id']);
            $table->dropForeign(['shipping_region_id']);
            $table->dropForeign(['payment_method_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_courier_id')->nullable()->change();
            $table->unsignedBigInteger('shipping_region_id')->nullable()->change();
            $table->unsignedBigInteger('payment_method_id')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('shipping_courier_id')->references('id')->on('shipping_couriers')->nullOnDelete();
            $table->foreign('shipping_region_id')->references('id')->on('shipping_regions')->nullOnDelete();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['shipping_courier_id']);
            $table->dropForeign(['shipping_region_id']);
            $table->dropForeign(['payment_method_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_courier_id')->nullable(false)->change();
            $table->unsignedBigInteger('shipping_region_id')->nullable(false)->change();
            $table->unsignedBigInteger('payment_method_id')->nullable(false)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('shipping_courier_id')->references('id')->on('shipping_couriers')->restrictOnDelete();
            $table->foreign('shipping_region_id')->references('id')->on('shipping_regions')->restrictOnDelete();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->restrictOnDelete();

            $table->dropUnique(['confirmation_token']);
            $table->dropColumn([
                'confirmation_token',
                'payment_method_name',
                'payment_method_details',
                'shipping_courier_name',
                'cancellation_reason',
            ]);
        });
    }
};
