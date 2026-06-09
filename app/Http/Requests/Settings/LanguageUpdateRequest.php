<?php

namespace App\Http\Requests\Settings;

use App\Support\LocaleResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LanguageUpdateRequest extends FormRequest
{
    /**
     * Normalize the "device language" choice (the `device` sentinel or an empty value) to null,
     * which means "no explicit preference — follow the browser/device language".
     */
    protected function prepareForValidation(): void
    {
        if (in_array($this->input('locale'), ['device', ''], true)) {
            $this->merge(['locale' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'locale' => ['nullable', Rule::in(app(LocaleResolver::class)->supported())],
        ];
    }
}
