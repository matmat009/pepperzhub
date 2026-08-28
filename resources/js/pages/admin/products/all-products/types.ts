/**
 * Product shape and shared helpers for the All Products screens.
 *
 * Mirrors the array returned by App\Http\Controllers\Admin\ProductController.
 * When that becomes a real Eloquent query, this type is the contract to keep it
 * honest.
 *
 * A product is a compound; what customers buy is a *format* (a variant) — a
 * vial of a given size, or a kit bundling a vial with the supplies to use it.
 * Price and stock therefore live on the variant, never on the product.
 */
export type ProductStatus = 'Active' | 'Draft' | 'Archived';

export type ProductVariant = {
    id: string;
    label: string;
    price: number;
    stock: number;
    is_kit: boolean;
    kit_inclusions: string[];
};

export type ProductImage = {
    /** Numeric once persisted; a local string id until it is uploaded. */
    id: string | number;
    url: string;
    /** Present only for a freshly picked file that still needs uploading. */
    file?: File | null;
};

export type CategoryOption = {
    id: number;
    name: string;
};

/**
 * A label/value pair in a repeatable list — "HPLC" / "99.2%", "Temperature" /
 * "2-8°C". Both technical subsections use this shape, so one list component
 * serves both.
 */
export type LabeledEntry = {
    id: string;
    label: string;
    value: string;
};

export type Product = {
    id: number;
    name: string;
    slug: string;
    short_description: string;
    full_description: string;
    /** Name, for the table's badge and category filter. */
    category: string;
    /** Id, for the form's select. */
    category_id: number;
    purity_entries: LabeledEntry[];
    storage_instructions: LabeledEntry[];
    status: ProductStatus;
    featured: boolean;
    images: ProductImage[];
    variants: ProductVariant[];
    created_at: string;
};

/**
 * Everything the two-column form edits, including the formats list and the
 * image gallery — so Show.vue's Cancel can revert all of it by reassigning one
 * object.
 */
export type ProductFormFields = {
    name: string;
    category_id: number | null;
    status: ProductStatus;
    featured: boolean;
    short_description: string;
    full_description: string;
    purity_entries: LabeledEntry[];
    storage_instructions: LabeledEntry[];
    variants: ProductVariant[];
    images: ProductImage[];
};

export const PRODUCT_STATUSES: ProductStatus[] = [
    'Active',
    'Draft',
    'Archived',
];

export const emptyProductForm = (): ProductFormFields => ({
    name: '',
    category_id: null,
    status: 'Draft',
    featured: false,
    short_description: '',
    full_description: '',
    purity_entries: [emptyEntry()],
    storage_instructions: [emptyEntry()],
    variants: [],
    images: [],
});

/**
 * Deep copy, so the form never writes through to the Inertia page props.
 * `structuredClone` throws on Inertia's reactive proxies; the payload is plain
 * JSON, so a round-trip is both safe and sufficient.
 */
export const toProductForm = (product: Product): ProductFormFields => ({
    name: product.name,
    category_id: product.category_id,
    status: product.status,
    featured: product.featured,
    short_description: product.short_description,
    full_description: product.full_description,
    purity_entries: withAtLeastOneRow(product.purity_entries),
    storage_instructions: withAtLeastOneRow(product.storage_instructions),
    variants: JSON.parse(JSON.stringify(product.variants)) as ProductVariant[],
    images: JSON.parse(JSON.stringify(product.images)) as ProductImage[],
});

export const emptyEntry = (): LabeledEntry => ({
    id: newLocalId('entry'),
    label: '',
    value: '',
});

/** Both lists keep a minimum of one row, so the section is never blank. */
export const withAtLeastOneRow = (entries: LabeledEntry[]): LabeledEntry[] => {
    const copy = JSON.parse(JSON.stringify(entries ?? [])) as LabeledEntry[];

    return copy.length ? copy : [emptyEntry()];
};

export const emptyVariant = (): ProductVariant => ({
    id: '',
    label: '',
    price: 0,
    stock: 0,
    is_kit: false,
    kit_inclusions: [],
});

/**
 * Ids for rows added in the browser. Always prefixed, so a local id can never
 * be mistaken for a database id — see `persistedId`.
 */
export const newLocalId = (prefix: string): string =>
    `${prefix}-${Math.random().toString(36).slice(2, 10)}`;

/**
 * The database id of a row, or null if this row only exists in the browser.
 *
 * Persisted rows arrive from the server with their numeric id; rows added in
 * the form carry a prefixed local id from `newLocalId`, which is never numeric.
 * The server reads a null id as "insert this", and re-checks any id it is given
 * against the product's own rows before trusting it.
 */
export const persistedId = (id: string | number): number | null => {
    const numeric = typeof id === 'number' ? id : Number(id);

    return Number.isInteger(numeric) && numeric > 0 ? numeric : null;
};

/**
 * Prices are in Philippine Peso. `narrowSymbol` pins the output to ₱ rather
 * than letting a thinner ICU build fall back to the "PHP" currency code.
 */
export const formatPrice = (value: number): string =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        currencyDisplay: 'narrowSymbol',
    }).format(value);

/** Headline purity for the list view: the first entry's value. */
export const primaryPurity = (entries: LabeledEntry[]): string =>
    entries?.[0]?.value?.trim() || '—';

export const formatDate = (value: string): string =>
    new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));

/** Cheapest format, for the "from $X" summary in the list. */
export const priceRange = (variants: ProductVariant[]): string => {
    if (variants.length === 0) {
        return '—';
    }

    const prices = variants.map((variant) => variant.price);
    const low = Math.min(...prices);
    const high = Math.max(...prices);

    return low === high
        ? formatPrice(low)
        : `${formatPrice(low)} – ${formatPrice(high)}`;
};

export const totalStock = (variants: ProductVariant[]): number =>
    variants.reduce((sum, variant) => sum + variant.stock, 0);

/**
 * Maps the form's shape onto what ProductRequest expects.
 *
 * Kept images are sent as ids so the controller can drop the rest; newly picked
 * ones ride along as files, which is what pushes Inertia into FormData mode.
 */
export const toSubmitPayload = (fields: ProductFormFields) => ({
    name: fields.name,
    category_id: fields.category_id,
    status: fields.status.toLowerCase(),
    featured: fields.featured,
    short_description: fields.short_description,
    full_description: fields.full_description,
    variants: fields.variants.map((variant) => ({
        id: persistedId(variant.id),
        label: variant.label,
        price: variant.price,
        stock: variant.stock,
        is_kit: variant.is_kit,
        kit_inclusions: variant.is_kit ? variant.kit_inclusions : [],
    })),
    purity: fields.purity_entries.map(({ id, label, value }) => ({
        id: persistedId(id),
        label,
        value,
    })),
    storage: fields.storage_instructions.map(({ id, label, value }) => ({
        id: persistedId(id),
        label,
        value,
    })),
    kept_image_ids: fields.images
        .filter((image) => !image.file)
        .map((image) => persistedId(image.id))
        .filter((id): id is number => id !== null),
    new_images: fields.images
        .filter((image) => image.file)
        .map((image) => image.file as File),
});
