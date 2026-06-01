<?php

namespace App\Enums;

use App\Models\ScoreProposal;

/**
 * The review state of a single {@see ScoreProposal} within a batch. A proposal is
 * Pending when first fetched/created, Edited once an admin adjusts it, Rejected to exclude it
 * from approval, and Applied once its batch is approved and the score is written to the fixture.
 */
enum ProposalStatus: string
{
    case Pending = 'pending';
    case Edited = 'edited';
    case Rejected = 'rejected';
    case Applied = 'applied';
}
