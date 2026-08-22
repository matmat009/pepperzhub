import type { ColumnDef } from '@tanstack/vue-table';
import type { Features } from '@/components/features';

/**
 * Column definitions for the categories table.
 *
 * Scaffold placeholder - fill in with `createColumnHelper` definitions, the
 * same way `@/components/DataTable.vue` builds its columns.
 *
 * Note: in @tanstack/vue-table v9 `ColumnDef` is generic over the table's
 * registered features as well as the row shape, so it takes `<Features, TData>`
 * rather than a single type argument.
 */
export const columns: ColumnDef<Features, any>[] = [];
