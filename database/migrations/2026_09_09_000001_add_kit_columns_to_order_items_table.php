<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kit contents, snapshotted onto the line like every other display field.
 *
 * A kit's inclusions are editable on the variant forever, so reading them live
 * would silently rewrite history: an order packed with four items would start
 * claiming five the moment the variant was edited. The line has to keep saying
 * what was actually shipped.
 *
 * Both columns are nullable and are deliberately not backfilled — null means
 * "never captured" for orders placed before this migration, which is different
 * from a false/empty kit flag on a line that was captured and simply was not a
 * kit. The detail screen renders nothing in either case.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('is_kit')->nullable()->after('variant_label');
            // json, matching product_variants.kit_inclusions — same shape in,
            // same shape out, so the cast on both models agrees.
            $table->json('kit_inclusions')->nullable()->after('is_kit');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['is_kit', 'kit_inclusions']);
        });
    }
};
