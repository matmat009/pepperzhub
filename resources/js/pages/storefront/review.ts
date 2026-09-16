export type StorefrontReview = {
    id: number;
    customer_name: string | null;
    title: string;
    description: string;
    created_at: string | null;
    image_url: string | null;
    /** Null for an untagged review, and for one whose product was deleted. */
    product_name: string | null;
    product_slug: string | null;
};

export type ReviewIdentity = Pick<StorefrontReview, 'customer_name'>;

/** Keep anonymous reviewers identical across every public review surface. */
export const reviewDisplayName = (review: ReviewIdentity): string =>
    review.customer_name?.trim() || 'Anonymous reviewer';
