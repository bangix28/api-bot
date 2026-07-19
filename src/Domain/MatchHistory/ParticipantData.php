<?php

namespace App\Domain\MatchHistory;

readonly class ParticipantData
{
    public function __construct(
        public string $puuid,
        public bool $win,
        public int $championId,
        public int $kills,
        public int $deaths,
        public int $assists,
    )
    {

    }
}