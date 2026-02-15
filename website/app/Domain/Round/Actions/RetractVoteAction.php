<?php

namespace App\Domain\Round\Actions;

use App\Infrastructure\Models\Player;
use App\Infrastructure\Models\Round;
use App\Infrastructure\Models\Vote;

class RetractVoteAction
{
    public function execute(Round $round, Player $voter): void
    {
        if (! $round->isVoting()) {
            throw new \InvalidArgumentException('Round is not in voting phase');
        }

        if ($round->vote_deadline && $round->vote_deadline->isPast()) {
            throw new \InvalidArgumentException('Voting deadline has passed');
        }

        $existingVote = Vote::whereIn('answer_id', $round->answers->pluck('id'))
            ->where('voter_id', $voter->id)
            ->first();

        if (! $existingVote) {
            throw new \InvalidArgumentException('No vote to retract');
        }

        $answer = $existingVote->answer;
        $existingVote->delete();
        $answer->recalculateVotes();
    }
}
