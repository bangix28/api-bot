<?php

namespace App\Domain\MatchHistory;

readonly class Runes
{
    public function __construct(
        public int $keystoneId,
        public int $primaryStyleId,
        public int $subStyleId,
        public int $statDefense,
        public int $statFlex,
        public int $statOffense,
    )
    {
    }
}
