<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dosage, frequency and duration for the public protocol reference.
 *
 * Plain nullable strings rather than a repeater: a product has at most one of
 * each, so they follow short_description rather than the technical-detail
 * lists. The free-form protocol notes that go with them are rows in
 * product_technical_details under a third type, so they need no column here.
 *
 * Nullable and deliberately not backfilled — null means the owner has not
 * written a protocol for this product, which is exactly what keeps it off
 * /protocols. Nothing is ever filled in on a product's behalf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('dosage')->nullable()->after('full_description');
            $table->string('frequency')->nullable()->after('dosage');
            $table->string('duration')->nullable()->after('frequency');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['dosage', 'frequency', 'duration']);
        });
    }
};
