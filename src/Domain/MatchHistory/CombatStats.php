<?php

namespace App\Domain\MatchHistory;

readonly class CombatStats
{
    public function __construct(
        public int $totalDamageDealtToChampions,
        public int $totalDamageTaken,
        public int $doubleKills,
        public int $tripleKills,
        public int $quadraKills,
        public int $pentaKills,
        public bool $firstBloodKill,
        public bool $gameEndedInSurrender,
    )
    {
    }
}
