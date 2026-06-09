<?php

namespace Tests\Feature\I18n;

use App\Enums\LeaderboardCategory;
use App\Enums\ScoringStrategy;
use App\Enums\TournamentStatus;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Covers the Brazilian-Portuguese localization: enum/data/validation strings resolve under pt_BR,
 * the English source strings stay as the fallback, and every Inertia response carries the locale
 * plus the translation bag the frontend reads.
 *
 * The suite default locale is pinned to `en` (phpunit.xml), so each pt_BR case sets the locale
 * explicitly — mirroring the production default without disturbing the rest of the suite.
 */
class LocalizationTest extends TestCase
{
    public function test_enums_data_and_validation_resolve_in_pt_br(): void
    {
        App::setLocale('pt_BR');

        // Enum labels (wrapped in __()).
        $this->assertSame('Geral', LeaderboardCategory::Overall->label());
        $this->assertSame('Artilheiro', LeaderboardCategory::GoalSniper->label());
        $this->assertSame('Chave por Fases', ScoringStrategy::PhasedBracket->label());
        $this->assertSame('Em andamento', TournamentStatus::InProgress->label());

        // Domain data resolved at display time, keyed by stable codes.
        $this->assertSame('Brasil', trans('countries.BRA'));
        $this->assertSame('Oitavas de final', trans('phases.round_of_16'));
        $this->assertSame('Cidade do México', trans('venues.Mexico City Stadium'));
        $this->assertSame('Vencedor', trans('brackets.winner'));

        // Framework validation messages.
        $this->assertSame('O campo nome é obrigatório.', __('validation.required', ['attribute' => 'nome']));

        // The tournament name translates; a pool source never does (no key, returns itself).
        $this->assertSame('Copa do Mundo 2026', __('World Cup 2026'));
        $this->assertSame('FF&A', __('FF&A'));
    }

    public function test_english_source_strings_are_the_fallback(): void
    {
        App::setLocale('en');

        // No en.json / en namespaces exist, so the English source string is returned verbatim.
        $this->assertSame('Overall', LeaderboardCategory::Overall->label());
        $this->assertSame('Upfront Bracket', ScoringStrategy::UpfrontBracket->label());

        // There is deliberately no English countries file: under English the frontend's tCountry()
        // falls back to the canonical `team.name` stored in the database (e.g. "Brazil").
        $this->assertFalse(Lang::has('countries.BRA'));
    }

    public function test_notification_subjects_drop_the_possessive_in_pt_br(): void
    {
        App::setLocale('pt_BR');

        $subject = __("🏆 You're top of :source's :pool", ['source' => 'FF&A', 'pool' => 'World Cup 2026']);

        $this->assertSame('🏆 Você está no topo do bolão da FF&A', $subject);
        $this->assertStringNotContainsString("'s", $subject);
    }

    public function test_inertia_responses_share_the_locale_and_translation_bag(): void
    {
        // The SetLocale middleware resolves pt_BR from the browser's Accept-Language header.
        $this->get('/', ['Accept-Language' => 'pt-BR'])->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'pt_BR')
            ->where('translations.ui.World Cup 2026', 'Copa do Mundo 2026')
            ->where('translations.countries.BRA', 'Brasil')
            ->where('translations.phases.round_of_16', 'Oitavas de final')
            ->where('translations.venues.Mexico City Stadium', 'Cidade do México')
            ->where('translations.brackets.winner', 'Vencedor')
        );
    }
}
