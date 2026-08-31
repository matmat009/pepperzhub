import {
    newLocalId,
    persistedId,
} from '@/pages/admin/products/all-products/types';

/**
 * Courier shapes for the admin screen.
 *
 * Mirrors App\Http\Controllers\Admin\ShippingCourierController's toRow().
 *
 * `newLocalId` / `persistedId` are imported rather than reimplemented: a region
 * row added in the browser carries a prefixed local id that is never numeric,
 * and `persistedId` turns that into the null the server reads as "insert this".
 * The server re-checks any numeric id against the courier's own rows, so a
 * stale or foreign id is inserted as new rather than hijacking another row.
 */
export type ShippingRegion = {
    id: number;
    name: string;
    note: string | null;
    rate: number;
    is_active: boolean;
    sort_order: number;
};

export type ShippingCourier = {
    id: number;
    name: string;
    is_active: boolean;
    sort_order: number;
    regions: ShippingRegion[];
};

/** A region row while it is being edited; `key` is client-only. */
export type RegionRow = {
    key: string;
    /** Numeric for a persisted row, a local string id for a new one. */
    id: number | string;
    name: string;
    note: string;
    rate: number | string;
    is_active: boolean;
};

export type CourierFormFields = {
    name: string;
    is_active: boolean;
    sort_order: number;
    regions: RegionRow[];
};

export const emptyRegionRow = (): RegionRow => ({
    key: newLocalId('region'),
    id: newLocalId('region'),
    name: '',
    note: '',
    rate: '',
    is_active: true,
});

export const emptyCourierForm = (): CourierFormFields => ({
    name: '',
    is_active: true,
    sort_order: 0,
    regions: [emptyRegionRow()],
});

export const toCourierForm = (courier: ShippingCourier): CourierFormFields => ({
    name: courier.name,
    is_active: courier.is_active,
    sort_order: courier.sort_order,
    regions: courier.regions.map((region) => ({
        key: newLocalId('region'),
        id: region.id,
        name: region.name,
        note: region.note ?? '',
        rate: region.rate,
        is_active: region.is_active,
    })),
});

/**
 * Strip the client-only `key` and resolve each row's id.
 *
 * Sending the real id back for rows that already exist is what makes the save
 * an id-preserving upsert rather than a delete-and-reinsert — orders point at
 * shipping_regions.id, so reissued ids would silently repoint history.
 */
export const toCourierPayload = (fields: CourierFormFields) => ({
    name: fields.name,
    is_active: fields.is_active,
    sort_order: fields.sort_order,
    regions: fields.regions.map((region) => ({
        id: persistedId(region.id),
        name: region.name,
        note: region.note,
        rate: region.rate,
        is_active: region.is_active,
    })),
});

/**
 * Explicit labels rather than a bare ucfirst() on the boolean. Deliberately
 * parallel to the payment-methods screen's map.
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
