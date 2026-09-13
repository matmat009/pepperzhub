/**
 * The storefront's own contact and social details, shared on every response by
 * HandleInertiaRequests.
 *
 * Every field is nullable: the operator fills these in as they get them, and
 * the storefront renders nothing at all for one that is still unset.
 */
export type SiteSettings = {
    contact_email: string | null;
    contact_phone: string | null;
    contact_address: string | null;
    facebook_url: string | null;
    instagram_url: string | null;
    tiktok_url: string | null;
};
