<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class SiteSettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every field is optional — the operator fills these in as they get them,
     * and the storefront omits whatever is still blank.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:255'],

            /*
             * Validated as URLs because they are rendered as hrefs. The footer's
             * icons and the FAQ nav item link straight to these, so a bare
             * "facebook.com/pepperzhub" would resolve against the storefront's
             * own origin and 404.
             */
            'facebook_url' => ['nullable', 'string', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'string', 'url', 'max:255'],
            'tiktok_url' => ['nullable', 'string', 'url', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'facebook_url.url' => 'Enter the full Facebook URL, including https://.',
            'instagram_url.url' => 'Enter the full Instagram URL, including https://.',
            'tiktok_url.url' => 'Enter the full TikTok URL, including https://.',
        ];
    }

    /**
     * Normalise all six fields to null-or-trimmed-value, present or not.
     *
     * Two things depend on this. A cleared input arrives as an empty string,
     * which would be stored as '' and read as "set" by the storefront's v-if —
     * a blank contact line under a live heading. And a field missing from the
     * payload has to mean "cleared" rather than "leave alone": this is a PUT
     * over a known set of columns, and $this->only() skips absent keys, so
     * without the explicit walk an empty submission would validate to an empty
     * array and change nothing.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(
            collect(array_keys($this->rules()))
                ->mapWithKeys(function (string $field): array {
                    $value = $this->input($field);

                    return [$field => blank($value) ? null : trim((string) $value)];
                })
                ->all()
        );
    }
}
