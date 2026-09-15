<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit trail behind every change to product_variants.stock.
 *
 * One row per movement, per variant — never per product. Stock lives on the
 * variant, and a product-level log would average two formats together, hiding
 * exactly the case this exists to surface: one format down to its last unit
 * while a healthy sibling keeps the total looking fine.
 *
 * resulting_stock is denormalised on purpose. It is what the variant held
 * immediately after this movement, so the log reads as a statement of fact at
 * a point in time rather than something that has to be replayed from the
 * current total backwards — and a later correction cannot silently rewrite
 * what an earlier entry meant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            /*
             * Cascades, unlike order_items.product_variant_id. An order line
             * has to stay readable after its variant is gone, so it snapshots
             * what it needs and nulls the link; a movement describes nothing
             * but the variant itself and means nothing without it.
             */
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            // Signed: negative takes stock out, positive puts it back.
            $table->integer('delta');
            $table->string('reason');
            $table->text('note')->nullable();
            $table->unsignedInteger('resulting_stock');
            $table->timestamps();

            /*
             * Id, not created_at, is the second key. An order that moves
             * several units and a correction applied in the same second have
             * to come back in the order they were written, and only the id
             * settles that.
             */
            $table->index(['product_variant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
