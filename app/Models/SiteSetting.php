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
    /**
     * Seeded into contact_email when the row is first created.
     *
     * The one field that ships with a value rather than starting null. Every
     * other detail is the operator's to supply, but the shop's own inbox is
     * known up front, and the Order Confirmation page's Gmail button is only
     * useful if it works on a fresh install rather than after someone
     * remembers to fill the field in. Still ordinary admin-editable data — the
     * profile form overwrites or clears it like any other.
     */
    public const DEFAULT_CONTACT_EMAIL = 'pepperzzhub@gmail.com';

    protected $fillable = [
        'contact_email',
        'contact_phone',
        'contact_address',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
    ];

    /**
     * The single settings row, created on first access.
     *
     * Get-or-create rather than a seeder: a seeder would have to run before the
     * first request on every environment, and DatabaseSeeder is deliberately
     * kept out of admin-managed data (see SeederSafetyTest). The defaults apply
     * to that first creation only — they are not reasserted over a row the
     * operator has since edited or cleared.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'contact_email' => self::DEFAULT_CONTACT_EMAIL,
        ]);
    }
}
