<?php

namespace App\Http\Requests\Manage;

use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\Import\CorrectedImport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the admin-reviewed values posted back from the review screen, then assembles them into
 * a {@see CorrectedImport} for the importer. Like the preview request it bypasses prediction-lock
 * checks; authorisation is the {@see manage-tournament} ability and v1 stays upfront-only.
 */
class BackfillCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-tournament') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $groupIds = $this->tournament()->groupFixtures()->pluck('id')->all();
        $knockoutIds = $this->tournament()->knockoutFixtures()->pluck('id')->all();

        return [
            'pool_id' => ['required', 'integer', Rule::exists('pools', 'id')->where('tournament_id', $this->tournament()->id)],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'overwrite' => ['sometimes', 'boolean'],

            'group' => ['present', 'array'],
            'group.*.fixture_id' => ['required', 'integer', Rule::in($groupIds)],
            'group.*.home_goals' => ['required', 'integer', 'min:0', 'max:99'],
            'group.*.away_goals' => ['required', 'integer', 'min:0', 'max:99'],

            'knockout' => ['present', 'array'],
            'knockout.*.fixture_id' => ['required', 'integer', Rule::in($knockoutIds)],
            'knockout.*.home_goals' => ['nullable', 'integer', 'min:0', 'max:99'],
            'knockout.*.away_goals' => ['nullable', 'integer', 'min:0', 'max:99'],
            'knockout.*.advancing_team_id' => ['nullable', 'integer'],

            'thirds_team_ids' => ['present', 'array'],
            'thirds_team_ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $pool = $this->pool();

            if ($pool !== null && ! $pool->predictsKnockoutBracket()) {
                $validator->errors()->add('pool_id', __('Backfill currently supports only upfront-bracket pools.'));
            }
        });
    }

    public function correctedImport(): CorrectedImport
    {
        $group = array_map(fn (array $row): array => [
            'fixture_id' => (int) $row['fixture_id'],
            'home_goals' => (int) $row['home_goals'],
            'away_goals' => (int) $row['away_goals'],
        ], $this->validated('group'));

        $knockout = array_map(fn (array $row): array => [
            'fixture_id' => (int) $row['fixture_id'],
            'home_goals' => $this->intOrNull($row['home_goals'] ?? null),
            'away_goals' => $this->intOrNull($row['away_goals'] ?? null),
            'advancing_pick' => $this->intOrNull($row['advancing_team_id'] ?? null),
        ], $this->validated('knockout'));

        $thirds = array_map('intval', $this->validated('thirds_team_ids', []));

        return new CorrectedImport($group, $knockout, $thirds);
    }

    public function tournament(): Tournament
    {
        $tournament = $this->route('tournament');

        return $tournament instanceof Tournament ? $tournament : Tournament::findOrFail($tournament);
    }

    public function pool(): ?Pool
    {
        return Pool::find($this->input('pool_id'));
    }

    public function targetUser(): User
    {
        return User::findOrFail($this->integer('user_id'));
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
