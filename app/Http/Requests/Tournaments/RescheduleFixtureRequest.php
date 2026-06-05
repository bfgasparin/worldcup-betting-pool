<?php

namespace App\Http\Requests\Tournaments;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Pool;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

class RescheduleFixtureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-tournament') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The kickoff arrives as a tz-naive wall-clock string (from a `datetime-local` input) that the
     * admin entered in their own browser timezone — its future-ness is checked in
     * {@see withValidator()}, since `after:now` would wrongly interpret it as UTC.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kicks_off_at' => ['required', 'date'],
            'venue' => ['required', Rule::in(array_keys($this->venueTimezones()))],
        ];
    }

    /**
     * Reject moving an already-finished fixture, and reject a kickoff that lands in the past once
     * read in the admin's timezone.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Fixture $fixture */
            $fixture = $this->route('fixture');

            if ($fixture->status === FixtureStatus::Finished) {
                $validator->errors()->add('kicks_off_at', __('A finished fixture cannot be rescheduled.'));

                return;
            }

            $kickoff = $this->parsedKickoff();

            if ($kickoff !== null && $kickoff->isPast()) {
                $validator->errors()->add('kicks_off_at', __('The new kickoff must be in the future.'));
            }
        });
    }

    /**
     * The validated new kickoff, read in the admin's browser timezone and normalised to UTC.
     */
    public function newKickoff(): CarbonInterface
    {
        return CarbonImmutable::parse($this->validated('kicks_off_at'), $this->adminTimezone())->utc();
    }

    /**
     * The chosen (existing) venue.
     */
    public function venue(): string
    {
        return (string) $this->validated('venue');
    }

    /**
     * The registered timezone for the chosen venue, stored alongside the fixture as metadata.
     */
    public function venueTimezone(): string
    {
        return $this->venueTimezones()[$this->venue()];
    }

    /**
     * The timezone the admin entered the kickoff in: their browser zone, shared via the same
     * `timezone` cookie the rest of the app renders times with. Falls back to the app timezone when
     * the cookie is missing or is not a valid IANA identifier.
     */
    public function adminTimezone(): string
    {
        $timezone = $this->cookie('timezone');

        if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        return (string) config('app.timezone');
    }

    /**
     * The kickoff parsed in the admin's timezone, or null when the kickoff input is not yet a valid
     * date (other rules will surface that).
     */
    private function parsedKickoff(): ?CarbonInterface
    {
        $kickoff = $this->input('kicks_off_at');

        if (! is_string($kickoff) || $kickoff === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($kickoff, $this->adminTimezone());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    private function venueTimezones(): array
    {
        /** @var Pool $pool */
        $pool = $this->route('pool');

        return $pool->tournament->venueTimezones();
    }
}
