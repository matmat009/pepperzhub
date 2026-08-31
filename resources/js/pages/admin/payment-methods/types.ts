import {
    newLocalId,
    persistedId,
} from '@/pages/admin/products/all-products/types';

/**
 * Payment method shapes for the admin screen.
 *
 * Mirrors App\Http\Controllers\Admin\PaymentMethodController's toRow(). The QR
 * code arrives as a URL, never a storage path.
 *
 * `newLocalId` / `persistedId` are imported rather than reimplemented — they
 * are the established convention for repeater rows, and two copies of the
 * "is this id real?" rule would be two things to keep in step.
 */
export type PaymentDetail = {
    label: string;
    value: string;
};

export type PaymentMethod = {
    id: number;
    name: string;
    details: PaymentDetail[];
    qr_code_url: string | null;
    is_active: boolean;
    sort_order: number;
    /** Orders still pointing at this row. Shown as context, never as a block. */
    order_count: number;
};

/** A detail row while it is being edited; `key` is client-only. */
export type DetailRow = PaymentDetail & { key: string };

export type PaymentMethodFormFields = {
    name: string;
    details: DetailRow[];
    is_active: boolean;
    sort_order: number;
    /** A newly chosen file, or null to leave the stored QR code alone. */
    qr_code: File | null;
    remove_qr_code: boolean;
};

export const emptyDetailRow = (): DetailRow => ({
    key: newLocalId('detail'),
    label: '',
    value: '',
});

export const emptyPaymentMethodForm = (): PaymentMethodFormFields => ({
    name: '',
    details: [emptyDetailRow()],
    is_active: true,
    sort_order: 0,
    qr_code: null,
    remove_qr_code: false,
});

export const toPaymentMethodForm = (
    method: PaymentMethod,
): PaymentMethodFormFields => ({
    name: method.name,
    // The list never drops below one row, so the section is never blank.
    details: (method.details.length
        ? method.details
        : [{ label: '', value: '' }]
    ).map((detail) => ({ ...detail, key: newLocalId('detail') })),
    is_active: method.is_active,
    sort_order: method.sort_order,
    qr_code: null,
    remove_qr_code: false,
});

/**
 * Strip the client-only `key` before submitting; the server validates
 * `details.*.label` and `details.*.value` and nothing else.
 */
export const toPaymentMethodPayload = (fields: PaymentMethodFormFields) => ({
    name: fields.name,
    details: fields.details.map(({ label, value }) => ({ label, value })),
    is_active: fields.is_active,
    sort_order: fields.sort_order,
    qr_code: fields.qr_code,
    remove_qr_code: fields.remove_qr_code,
});

export { persistedId };

/**
 * Explicit labels rather than a bare ucfirst() on the boolean — the badge text
 * says what the flag actually does, which "Active"/"Inactive" alone does not.
 * The shipping-couriers screen carries a deliberately parallel map.
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
