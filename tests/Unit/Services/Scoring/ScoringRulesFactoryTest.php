<?php

namespace Tests\Unit\Services\Scoring;

use App\Enums\ScoringStrategy;
use App\Services\Scoring\ScoringRulesFactory;
use App\Services\Scoring\Strategies\WorldCupStandardRules;
use Tests\TestCase;

class ScoringRulesFactoryTest extends TestCase
{
    public function test_it_resolves_the_world_cup_standard_rules(): void
    {
        $factory = new ScoringRulesFactory;

        $this->assertInstanceOf(
            WorldCupStandardRules::class,
            $factory->make(ScoringStrategy::WorldCupStandard),
        );
    }
}
