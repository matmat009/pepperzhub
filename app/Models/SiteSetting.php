<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The storefront's own contact and social details — one row, always.
 *
 * Read through current(), never queried directly: HandleInertiaRequests shares
 * this on every response, so a null here would mean the footer and nav each
 * doing their own null-checking on a row that simply has not been created yet.
 */
class SiteSetting extends Model
{
    protected $fillable = [
        'contact_email',
        'contact_phone',
        'contact_address',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
    ];

    /**
     * The single settings row, created empty on first access.
     *
     * Get-or-create rather than a seeder: a seeder would have to run before the
     * first request on every environment, and DatabaseSeeder is deliberately
     * kept out of admin-managed data (see SeederSafetyTest). An all-null row is
     * the correct initial state anyway — the storefront renders nothing for a
     * field that has not been set.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
