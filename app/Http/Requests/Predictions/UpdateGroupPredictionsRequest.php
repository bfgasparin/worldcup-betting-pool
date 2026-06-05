<?php

namespace App\Http\Requests\Predictions;

use Illuminate\Validation\Rule;

class UpdateGroupPredictionsRequest extends PredictionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $groupFixtureIds = $this->pool()->tournament->groupFixtures()->pluck('id')->all();

        return [
            'predictions' => ['required', 'array'],
            'predictions.*.fixture_id' => ['required', 'integer', Rule::in($groupFixtureIds)],
            'predictions.*.home_goals' => ['required', 'integer', 'min:0', 'max:99'],
            'predictions.*.away_goals' => ['required', 'integer', 'min:0', 'max:99'],
        ];
    }
}
