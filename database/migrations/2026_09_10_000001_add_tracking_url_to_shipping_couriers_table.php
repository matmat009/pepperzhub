<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a customer goes to chase the parcel on the courier's own site.
 *
 * Deliberately not snapshotted onto the order, unlike the courier name beside
 * it: the name is what the customer was told and must never change, but the URL
 * is infrastructure. Filling one in later should light up the link on every
 * order already shipped with that courier, which a snapshot would prevent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_couriers', function (Blueprint $table) {
            $table->string('tracking_url')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_couriers', function (Blueprint $table) {
            $table->dropColumn('tracking_url');
        });
    }
};
