<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_courier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('note')->nullable();
            // Same precision as product_variants.price.
            $table->decimal('rate', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['shipping_courier_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_regions');
    }
};
