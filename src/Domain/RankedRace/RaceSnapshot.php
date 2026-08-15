<?php

namespace App\Domain\RankedRace;

use App\Domain\RiotAccount\RankedQueueEntity;

/** Photo du rang d'un joueur à un instant donné, relue pour la course. */
readonly class RaceSnapshot
{
    public function __construct(
        public RacePlayer $player,
        public \DateTimeImmutable $capturedAt,
        public RankedQueueEntity $ranked,
    ) {
    }

    public function raceScore(): int
    {
        return RaceScore::of($this->ranked);
    }

    public function games(): int
    {
        return $this->ranked->getWins() + $this->ranked->getLosses();
    }
}
