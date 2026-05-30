<?php

namespace App\Http\Requests\Predictions;

use App\Services\Predictions\BracketResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateKnockoutPredictionsRequest extends PredictionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $knockoutFixtureIds = $this->tournament()->knockoutFixtures()->pluck('id')->all();

        return [
            'predictions' => ['required', 'array'],
            'predictions.*.fixture_id' => ['required', 'integer', Rule::in($knockoutFixtureIds)],
            'predictions.*.home_goals' => ['nullable', 'integer', 'min:0', 'max:99'],
            'predictions.*.away_goals' => ['nullable', 'integer', 'min:0', 'max:99'],
            'predictions.*.advancing_team_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * A predicted advancing team must be one of the two teams the engine has resolved for
     * that fixture from the user's earlier picks — never trust the client to send a valid id.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $resolved = app(BracketResolver::class)->resolve($this->entry())->resolved;

            foreach ((array) $this->input('predictions', []) as $index => $prediction) {
                $advancing = $prediction['advancing_team_id'] ?? null;

                if ($advancing === null) {
                    continue;
                }

                $slot = $resolved[$prediction['fixture_id'] ?? null] ?? ['home' => null, 'away' => null];

                if ((int) $advancing !== $slot['home'] && (int) $advancing !== $slot['away']) {
                    $validator->errors()->add("predictions.{$index}.advancing_team_id", 'The selected team is not in this match.');
                }
            }
        });
    }
}
