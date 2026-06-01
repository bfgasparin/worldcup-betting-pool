<?php

namespace Tests\Unit\Models;

use App\Enums\BatchStatus;
use App\Enums\ProposalStatus;
use App\Models\ScoreBatch;
use App\Models\ScoreProposal;
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
