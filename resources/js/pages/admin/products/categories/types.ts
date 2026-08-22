/**
 * Category shape for the Categories screen.
 *
 * Mirrors App\Http\Controllers\Admin\CategoryController. `product_count` is a
 * denormalised count today; it becomes a withCount() on the real query.
 */
export type Category = {
    id: number;
    name: string;
    description: string;
    product_count: number;
    created_at: string;
};

export type CategoryFormFields = {
    name: string;
    description: string;
};

export const emptyCategoryForm = (): CategoryFormFields => ({
    name: '',
    description: '',
});

export const toCategoryForm = (category: Category): CategoryFormFields => ({
    name: category.name,
    description: category.description,
});

export const formatDate = (value: string): string =>
    new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));

/** A category holding products cannot be deleted until they are reassigned. */
export const blockedReason = (category: Category | null): string | null => {
    if (!category || category.product_count === 0) {
        return null;
    }

    const subject =
        category.product_count === 1
            ? 'this 1 product'
            : `these ${category.product_count} products`;

    return `Reassign ${subject} before deleting this category.`;
};
