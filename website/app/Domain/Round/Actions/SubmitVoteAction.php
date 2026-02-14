<?php

namespace App\Domain\Round\Actions;

use App\Infrastructure\Models\Answer;
use App\Infrastructure\Models\Player;
use App\Infrastructure\Models\Round;
use App\Infrastructure\Models\Vote;

class SubmitVoteAction
{
    public function execute(Round $round, Player $voter, Answer $answer): Vote
    {
        if (! $round->isVoting()) {
            throw new \InvalidArgumentException('Round is not in voting phase');
        }

        if ($round->vote_deadline && $round->vote_deadline->isPast()) {
            throw new \InvalidArgumentException('Voting deadline has passed');
        }

        // Check voter is in the game
        $isInGame = $round->game->activePlayers()
            ->where('players.id', $voter->id)
            ->exists();

        if (! $isInGame) {
            throw new \InvalidArgumentException('Player is not in this game');
        }

        // Can't vote for own answer
        if ($answer->player_id === $voter->id) {
            throw new \InvalidArgumentException('Cannot vote for your own answer');
        }

        // Check answer belongs to this round
        if ($answer->round_id !== $round->id) {
            throw new \InvalidArgumentException('Answer does not belong to this round');
        }

        // Check if already voted in this round
        $existingVote = Vote::whereIn('answer_id', $round->answers->pluck('id'))
            ->where('voter_id', $voter->id)
            ->first();

        if ($existingVote) {
            // Change vote
            $oldAnswer = $existingVote->answer;
            $existingVote->update([
                'answer_id' => $answer->id,
                'voter_nickname' => $voter->nickname,
            ]);
            $oldAnswer->recalculateVotes();
            $answer->recalculateVotes();

            return $existingVote->fresh();
        }

        $vote = Vote::create([
            'answer_id' => $answer->id,
            'voter_id' => $voter->id,
            'voter_nickname' => $voter->nickname,
        ]);

        $answer->recalculateVotes();

        return $vote;
    }
}
