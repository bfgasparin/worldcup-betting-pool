<?php

namespace App\Http\Requests\Concerns;

use App\Enums\OrderingScope;
use App\Services\Predictions\TieState;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Shared rules and validation for the manual tie-ordering endpoints (user and admin). A submitted
 * order must be a permutation of exactly one currently-unresolved tie, so a stale submission (the
 * tie changed or vanished since the page loaded) is rejected rather than silently misapplied.
 */
trait ValidatesTieOrdering
{
    /**
     * @param  list<string>  $groupNames  the tournament's group names, for the within-group scope
     * @return array<string, mixed>
     */
    protected function tieOrderingRules(array $groupNames): array
    {
        return [
            'scope' => ['required', Rule::enum(OrderingScope::class)],
            'group' => ['required_if:scope,'.OrderingScope::WithinGroup->value, 'nullable', Rule::in($groupNames)],
            'ordered_team_ids' => ['required', 'array', 'min:2'],
            'ordered_team_ids.*' => ['integer'],
        ];
    }

    /**
     * Assert the submitted order matches a live unresolved tie in the given state.
     */
    protected function validateOrderingAgainst(Validator $validator, TieState $state): void
    {
        $scope = OrderingScope::tryFrom((string) $this->input('scope'));
        $ids = array_map('intval', (array) $this->input('ordered_team_ids', []));

        $matches = match ($scope) {
            OrderingScope::Thirds => $this->isSameSet($ids, $state->thirds),
            OrderingScope::WithinGroup => $state->matchingGroupCluster((string) $this->input('group'), $ids) !== null,
            default => false,
        };

        if (! $matches) {
            $validator->errors()->add('ordered_team_ids', __('These teams are no longer tied — refresh and try again.'));
        }
    }

    /**
     * @param  list<int>  $a
     * @param  list<int>  $b
     */
    private function isSameSet(array $a, array $b): bool
    {
        if ($b === [] || count($a) !== count($b)) {
            return false;
        }

        sort($a);
        sort($b);

        return $a === $b;
    }
}
