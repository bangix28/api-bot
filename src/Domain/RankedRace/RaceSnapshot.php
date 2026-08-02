<?php

namespace App\Domain\RankedRace;

use App\Domain\RiotAccount\RankedQueueEntity;

/** Snapshot quotidien relu pour la course (côté lecture du snapshot elo). */
readonly class RaceSnapshot
{
    public function __construct(
        public RacePlayer $player,
        public \DateTimeImmutable $day,
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
