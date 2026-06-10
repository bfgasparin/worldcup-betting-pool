<?php

namespace App\Http\Requests\Manage;

use App\Concerns\ProfileValidationRules;
use App\Models\Pool;
use App\Models\User;
use App\Support\LocaleResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePlayerRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-tournament') ?? false;
    }

    /**
     * Normalize the "device language" choice (the `device` sentinel or an empty value) to null —
     * "no explicit preference, follow the device language" — matching the language settings form.
     */
    protected function prepareForValidation(): void
    {
        if (in_array($this->input('locale'), ['device', ''], true)) {
            $this->merge(['locale' => null]);
        }

        // Treat a blank email field as "no email yet" rather than an invalid address.
        if ($this->input('email') === '') {
            $this->merge(['email' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            // Optional at pre-registration. When given, the email is vouched for and the account is
            // immediately locked to admin edits (same rule as setting it later via setEmail). Uses
            // the same strict format check as emailRules() so incomplete addresses are rejected.
            'email' => ['nullable', 'string', 'email:strict', 'max:255', Rule::unique(User::class)],
        ]);
    }

    /**
     * Rules shared by pre-registration and edit. The email lives only on the store form and the
     * dedicated set-email action, so it is deliberately not part of the shared set.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function commonRules(): array
    {
        return [
            'name' => $this->nameRules(),
            'phone' => $this->phoneRules(),
            'locale' => ['nullable', Rule::in(app(LocaleResolver::class)->supported())],
            'pools' => ['array'],
            'pools.*' => ['integer', Rule::exists(Pool::class, 'id')],
        ];
    }

    /**
     * Reject any pool that exists but no longer accepts predictions — mirrors the user:pre-register
     * command, which refuses to pre-join a player into a locked pool.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->poolModels() as $pool) {
                if (! $pool->acceptsPredictions()) {
                    $validator->errors()->add('pools', __('Pool ":name" is no longer accepting predictions.', ['name' => $pool->name]));
                }
            }
        });
    }

    /**
     * The submitted pools resolved to models (existence is enforced by the rules above).
     *
     * @return Collection<int, Pool>
     */
    public function poolModels(): Collection
    {
        /** @var array<int, mixed> $pools */
        $pools = (array) $this->input('pools', []);
        $ids = array_values(array_unique(array_map('intval', $pools)));

        if ($ids === []) {
            return new Collection;
        }

        return Pool::whereIn('id', $ids)->get();
    }
}
