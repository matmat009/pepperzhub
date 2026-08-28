<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

abstract class Controller
{
    /**
     * Flash a toast for the next Inertia response.
     *
     * Must go through Inertia::flash() rather than RedirectResponse::with():
     * Inertia reads its flash bag from the `inertia.flash_data` session key and
     * exposes it as the page's top-level `flash` prop, which is what fires the
     * client `flash` event. A plain ->with('success', ...) lands in the ordinary
     * session bag, which that event never looks at.
     *
     * The payload shape is the contract with the `FlashToast` type in
     * resources/js/types/ui.ts, consumed by resources/js/lib/flashToast.ts.
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', [
            'type' => $type,
            'message' => $message,
        ]);
    }
}
