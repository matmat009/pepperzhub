<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site-wide storefront contact details, as a single row.
 *
 * A one-row table rather than a key/value store: the six fields are known and
 * fixed, so columns give them types and let the whole set load in one read.
 * Nothing enforces the single row at the database level — App\Models\SiteSetting
 * owns that invariant, since a UNIQUE on a constant would need a sentinel column
 * that exists only to be checked.
 *
 * Every column is nullable. The operator fills these in when they have them,
 * and the footer and nav drop whatever is still blank rather than rendering an
 * empty line or a link to nowhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_address')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
