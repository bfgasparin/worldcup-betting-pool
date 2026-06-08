<?php

namespace Tests\Unit\Models;

use App\Enums\BatchStatus;
use App\Enums\ProposalStatus;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
use App\Models\Tournament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_batch_casts_status_and_exposes_its_proposals(): void
    {
        $batch = ScoreBatch::factory()->create();
        $proposal = ScoreProposal::factory()->for($batch, 'batch')->create([
            'status' => ProposalStatus::Pending,
        ]);

        $this->assertInstanceOf(BatchStatus::class, $batch->status);
        $this->assertSame(BatchStatus::Open, $batch->status);
        $this->assertTrue($batch->proposals->contains($proposal));
        $this->assertInstanceOf(ProposalStatus::class, $proposal->fresh()->status);
        $this->assertTrue($proposal->batch->is($batch));
        $this->assertTrue($proposal->fixture->is($proposal->fixture));
    }

    public function test_open_for_returns_the_single_open_batch_creating_it_with_the_given_source(): void
    {
        $tournament = Tournament::factory()->create();

        $batch = ScoreBatch::openFor($tournament, 'live');

        $this->assertSame(BatchStatus::Open, $batch->status);
        $this->assertSame('live', $batch->source);
        $this->assertTrue($batch->tournament->is($tournament));

        // A second call reuses the same open batch and never changes the original source.
        $again = ScoreBatch::openFor($tournament, 'manual');
        $this->assertTrue($again->is($batch));
        $this->assertSame('live', $again->source);
        $this->assertSame(1, $tournament->scoreBatches()->count());
    }

    public function test_a_fixture_may_only_be_proposed_once_per_batch(): void
    {
        $proposal = ScoreProposal::factory()->create();

        $this->expectException(QueryException::class);

        ScoreProposal::factory()->create([
            'score_batch_id' => $proposal->score_batch_id,
            'fixture_id' => $proposal->fixture_id,
        ]);
    }
}
