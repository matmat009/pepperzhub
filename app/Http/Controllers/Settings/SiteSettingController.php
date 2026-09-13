<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SiteSettingUpdateRequest;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The storefront's contact and social details.
 *
 * Its own controller rather than a second branch of ProfileController::update:
 * the two forms share a page but nothing else. The account fields belong to the
 * operator's login, these belong to the shop, and folding them together would
 * mean either form resubmitting the other's values on every save.
 *
 * There is no edit() here — the form renders inside settings/Profile, and the
 * values it starts from come off the siteSettings shared prop, which is already
 * on every response.
 */
class SiteSettingController extends Controller
{
    public function update(SiteSettingUpdateRequest $request): RedirectResponse
    {
        SiteSetting::current()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Storefront contact info updated.')]);

        return to_route('profile.edit');
    }
}
