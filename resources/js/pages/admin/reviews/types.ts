/**
 * Review shapes for the admin screen.
 *
 * Mirrors App\Http\Controllers\Admin\ReviewController's toRow(). The photo
 * arrives as a URL, never a storage path.
 *
 * The product tag is nullable throughout — a review may be about the shop
 * rather than any one product, and reviews.product_id is nullOnDelete, so a
 * tagged review becomes untagged rather than disappearing when its product is
 * deleted.
 */
export type Review = {
    id: number;
    product_id: number | null;
    product_name: string | null;
    customer_name: string | null;
    title: string;
    description: string;
    image_url: string | null;
    is_active: boolean;
    sort_order: number;
};

/** The select's options: every product, active or not. */
export type ReviewProductOption = {
    id: number;
    name: string;
};

/**
 * The select's "no product" value.
 *
 * A sentinel rather than an empty string: reka-ui reserves '' for the
 * placeholder state, so an option carrying it can never be chosen. It is
 * translated back to '' on submit, which the request reads as null.
 */
export const NO_PRODUCT = 'none';

export type ReviewFormFields = {
    /** A product id as a string, or NO_PRODUCT. */
    product_id: string;
    customer_name: string;
    title: string;
    description: string;
    is_active: boolean;
    sort_order: number;
    /** A newly chosen file, or null to leave the stored photo alone. */
    image: File | null;
    remove_image: boolean;
};

export const emptyReviewForm = (): ReviewFormFields => ({
    product_id: NO_PRODUCT,
    customer_name: '',
    title: '',
    description: '',
    is_active: true,
    sort_order: 0,
    image: null,
    remove_image: false,
});

export const toReviewForm = (review: Review): ReviewFormFields => ({
    product_id:
        review.product_id === null ? NO_PRODUCT : String(review.product_id),
    customer_name: review.customer_name ?? '',
    title: review.title,
    description: review.description,
    is_active: review.is_active,
    sort_order: review.sort_order,
    image: null,
    remove_image: false,
});

/**
 * The empty select value goes back as an empty string; the request's
 * prepareForValidation turns that into a real null rather than letting it fail
 * the `exists` rule.
 */
export const toReviewPayload = (fields: ReviewFormFields) => ({
    product_id: fields.product_id === NO_PRODUCT ? '' : fields.product_id,
    customer_name: fields.customer_name,
    title: fields.title,
    description: fields.description,
    is_active: fields.is_active,
    sort_order: fields.sort_order,
    image: fields.image,
    remove_image: fields.remove_image,
});

/**
 * Deliberately parallel to the payment-methods and shipping-couriers maps —
 * the same badge should read the same way on every admin screen.
 */
export const availabilityLabels = {
    active: 'Active',
    inactive: 'Inactive',
} as const;

export const availabilityTone = {
    active: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    inactive: 'border-neutral-200 bg-neutral-100 text-neutral-700',
} as const;

export const availabilityKey = (isActive: boolean): 'active' | 'inactive' =>
    isActive ? 'active' : 'inactive';
