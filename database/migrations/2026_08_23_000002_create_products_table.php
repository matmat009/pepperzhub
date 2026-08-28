<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Restricted rather than cascading: deleting a category that still
            // holds products is blocked in CategoryController, and the FK is
            // the backstop for that rule.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->boolean('featured')->default(false);
            $table->string('short_description')->nullable();
            $table->text('full_description')->nullable();
            $table->timestamps();

            $table->index(['status', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
