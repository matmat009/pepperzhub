/**
 * Product shape and shared helpers for the All Products screens.
 *
 * Mirrors the array returned by App\Http\Controllers\Admin\ProductController.
 * When that method becomes a real Eloquent query, this type is the contract to
 * keep it honest.
 */
export type ProductType = 'Kit' | 'Vial';

export type ProductStatus = 'Active' | 'Draft' | 'Archived';

export type Product = {
    id: number;
    name: string;
    description: string;
    type: ProductType;
    category: string;
    purity: number;
    status: ProductStatus;
    stock: number;
    price: number;
    thumbnail: string | null;
    created_at: string;
};

/** Editable subset of a product, as bound by ProductForm. */
export type ProductFormFields = {
    name: string;
    type: ProductType;
    category: string;
    purity: number | string;
    price: number | string;
    stock: number | string;
    status: ProductStatus;
    description: string;
    thumbnail: File | null;
};

export const PRODUCT_TYPES: ProductType[] = ['Kit', 'Vial'];

export const PRODUCT_STATUSES: ProductStatus[] = [
    'Active',
    'Draft',
    'Archived',
];

export const PRODUCT_CATEGORIES: string[] = [
    'Healing',
    'Recovery',
    'Cosmetic',
    'Growth',
    'Metabolic',
];

export const emptyProductForm = (): ProductFormFields => ({
    name: '',
    type: 'Vial',
    category: '',
    purity: '',
    price: '',
    stock: '',
    status: 'Draft',
    description: '',
    thumbnail: null,
});

export const toProductForm = (product: Product): ProductFormFields => ({
    name: product.name,
    type: product.type,
    category: product.category,
    purity: product.purity,
    price: product.price,
    stock: product.stock,
    status: product.status,
    description: product.description,
    thumbnail: null,
});

export const formatPrice = (value: number): string =>
    new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(value);

export const formatPurity = (value: number): string => `${value.toFixed(1)}%`;

export const formatDate = (value: string): string =>
    new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
