<?php

namespace App\Domain\MatchHistory;

readonly class ScoreLine
{
    public function __construct(
        public int $kills,
        public int $deaths,
        public int $assists,
        public int $champLevel,
        public int $goldEarned,
        public int $creepScore,
        public int $visionScore,
    )
    {
    }
}
