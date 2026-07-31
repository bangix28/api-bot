<?php

namespace App\Domain\MatchHistory;

readonly class MatchData
{
    /**
     * @param string $matchId
     * @param int $queueId
     * @param int $gameEndTimeStamp
     * @param int $gameDuration
     * @param ParticipantData[] $participants
     */
    public function __construct(
        public string          $matchId,
        public int             $queueId,
        public int             $gameEndTimeStamp,
        public int             $gameDuration,
        public array           $participants,
    )
    {

    }
}
