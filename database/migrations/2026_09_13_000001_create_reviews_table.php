<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer testimonials: a photo and a short write-up, no scores.
 *
 * Deliberately no rating column. The reference material carries none, and a
 * nullable one would invite an average nobody asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            /*
             * Optional tag, not an owner. A review with no product still shows
             * on /reviews, and nullOnDelete means removing a product untags its
             * reviews rather than deleting them — unlike an order line, nothing
             * here has to stay historically accurate, so the testimonial simply
             * outlives the product it mentioned.
             */
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('title');
            $table->text('description');
            // Business asset shown to every customer: the PUBLIC disk, same
            // reasoning as payment_methods.qr_code_path.
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
