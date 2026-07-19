<?php

namespace App\Domain\MatchHistory;

readonly class MatchData
{
    /**
     * @param int $gameEndTimeStamp
     * @param int $gameDuration
     * @param ParticipantData[] $participants
     */
    public function __construct(
        public int             $gameEndTimeStamp,
        public int             $gameDuration,
        public array           $participants,
    )
    {

    }
}