<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Label/value pairs — "Account Number" / "0012 3456 7890". Shapes
            // differ per method (a bank needs three rows, GCash one), so this
            // follows kit_inclusions rather than becoming fixed columns.
            $table->json('details')->nullable();
            // Business asset shown to every customer: lives on the PUBLIC disk,
            // unlike orders.payment_proof_path which is private.
            $table->string('qr_code_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
