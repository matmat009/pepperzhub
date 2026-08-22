<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    /**
     * Placeholder stock records.
     *
     * Same five products as ProductController, with the movement log that a
     * StockMovement model will eventually own. `stock` is the running total and
     * matches the `resulting_stock` of the most recent history entry.
     *
     * @return array<int, array<string, mixed>>
     */
    private function inventory(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'BPC-157',
                'type' => 'Vial',
                'category' => 'Healing',
                'thumbnail' => null,
                'stock' => 142,
                'updated_at' => '2026-04-02',
                'history' => [
                    ['id' => 1, 'date' => '2026-01-14', 'delta' => 120, 'reason' => 'Restock', 'resulting_stock' => 120, 'note' => 'Initial intake, lot A-1140.'],
                    ['id' => 2, 'date' => '2026-02-21', 'delta' => -18, 'reason' => 'Order Fulfilled', 'resulting_stock' => 102, 'note' => null],
                    ['id' => 3, 'date' => '2026-04-02', 'delta' => 40, 'reason' => 'Restock', 'resulting_stock' => 142, 'note' => 'Lot A-1188.'],
                ],
            ],
            [
                'id' => 2,
                'name' => 'TB-500',
                'type' => 'Kit',
                'category' => 'Recovery',
                'thumbnail' => null,
                'stock' => 64,
                'updated_at' => '2026-03-30',
                'history' => [
                    ['id' => 1, 'date' => '2026-02-03', 'delta' => 80, 'reason' => 'Restock', 'resulting_stock' => 80, 'note' => null],
                    ['id' => 2, 'date' => '2026-03-11', 'delta' => -12, 'reason' => 'Order Fulfilled', 'resulting_stock' => 68, 'note' => null],
                    ['id' => 3, 'date' => '2026-03-30', 'delta' => -4, 'reason' => 'Damaged', 'resulting_stock' => 64, 'note' => 'Seal failure in transit.'],
                ],
            ],
            [
                'id' => 3,
                'name' => 'GHK-Cu',
                'type' => 'Vial',
                'category' => 'Cosmetic',
                'thumbnail' => null,
                'stock' => 0,
                'updated_at' => '2026-04-11',
                'history' => [
                    ['id' => 1, 'date' => '2026-03-22', 'delta' => 24, 'reason' => 'Restock', 'resulting_stock' => 24, 'note' => null],
                    ['id' => 2, 'date' => '2026-04-05', 'delta' => -20, 'reason' => 'Order Fulfilled', 'resulting_stock' => 4, 'note' => 'Bulk order #1042.'],
                    ['id' => 3, 'date' => '2026-04-11', 'delta' => -4, 'reason' => 'Order Fulfilled', 'resulting_stock' => 0, 'note' => null],
                ],
            ],
            [
                'id' => 4,
                'name' => 'Ipamorelin',
                'type' => 'Vial',
                'category' => 'Growth',
                'thumbnail' => null,
                'stock' => 8,
                'updated_at' => '2026-02-16',
                'history' => [
                    ['id' => 1, 'date' => '2025-11-09', 'delta' => 30, 'reason' => 'Restock', 'resulting_stock' => 30, 'note' => null],
                    ['id' => 2, 'date' => '2026-01-08', 'delta' => -19, 'reason' => 'Order Fulfilled', 'resulting_stock' => 11, 'note' => null],
                    ['id' => 3, 'date' => '2026-02-16', 'delta' => -3, 'reason' => 'Correction', 'resulting_stock' => 8, 'note' => 'Recount after audit.'],
                ],
            ],
            [
                'id' => 5,
                'name' => 'Semaglutide',
                'type' => 'Kit',
                'category' => 'Metabolic',
                'thumbnail' => null,
                'stock' => 27,
                'updated_at' => '2026-04-18',
                'history' => [
                    ['id' => 1, 'date' => '2026-04-18', 'delta' => 30, 'reason' => 'Restock', 'resulting_stock' => 30, 'note' => 'First batch.'],
                    ['id' => 2, 'date' => '2026-04-18', 'delta' => -3, 'reason' => 'Damaged', 'resulting_stock' => 27, 'note' => 'Cold chain break.'],
                ],
            ],
        ];
    }

    public function index(): Response
    {
        return Inertia::render('admin/products/inventory/Index', [
            'items' => $this->inventory(),
        ]);
    }

    /**
     * Stub: no persistence yet. The page applies the adjustment to its own
     * client-side copy so the table and history reflect it immediately.
     */
    public function adjust(Request $request, int $product): RedirectResponse
    {
        return back()->with('success', 'Stock adjusted.');
    }
}
