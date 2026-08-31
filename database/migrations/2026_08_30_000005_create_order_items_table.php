<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Nullable: deleting a product must never destroy order history.
            // Everything the line needs to render is snapshotted below, so a
            // null variant leaves the order fully readable.
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            $table->string('product_name');
            $table->string('variant_label');
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('line_total', 10, 2)->default(0);
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
