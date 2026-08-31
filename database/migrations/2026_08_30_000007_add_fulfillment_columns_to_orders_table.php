<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fulfillment timestamps and the carrier actually used.
 *
 * Plain nullable columns rather than a status-movement table: at one order at a
 * time and one admin, a ledger would be scaffolding for reporting nobody has
 * asked for yet — the same reasoning that shelved Inventory. Each column
 * answers "when did this happen", which is all the detail screen needs. A
 * history table can come later if usage ever demands it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_verified_at')->nullable()->after('payment_status');
            $table->timestamp('processing_at')->nullable()->after('order_status');
            $table->timestamp('shipped_at')->nullable()->after('processing_at');
            $table->timestamp('completed_at')->nullable()->after('shipped_at');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');

            /*
             * The courier picked at checkout was a shipping-rate quote, not a
             * commitment. What actually carried the parcel can differ, so it is
             * captured separately at ship time and defaults to the snapshot.
             */
            $table->string('shipped_via')->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_verified_at',
                'processing_at',
                'shipped_at',
                'completed_at',
                'cancelled_at',
                'shipped_via',
            ]);
        });
    }
};
