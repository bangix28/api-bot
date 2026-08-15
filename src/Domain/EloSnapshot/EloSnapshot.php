<?php

namespace App\Domain\EloSnapshot;

use App\Domain\RiotAccount\RankedQueueEntity;

/**
 * Photo horodatée du rang d'un compte dans une file, prise au fil de l'eau.
 *
 * Distinct de DailyEloSnapshot, qui reste la maille quotidienne alimentant la
 * courbe publique /elo-daily : cycle de vie, volumétrie et usage différents.
 */
readonly class EloSnapshot
{
    public function __construct(
        public string $puuid,
        public \DateTimeImmutable $capturedAt,
        public RankedQueueType $queue,
        public RankedQueueEntity $ranked,
    ) {
    }

    /** Le rang a-t-il bougé par rapport à un autre relevé ? */
    public function hasSameRankAs(self $other): bool
    {
        return $this->ranked->getTier() === $other->ranked->getTier()
            && $this->ranked->getDivision() === $other->ranked->getDivision()
            && $this->ranked->getLeaguePoints() === $other->ranked->getLeaguePoints()
            && $this->ranked->getWins() === $other->ranked->getWins()
            && $this->ranked->getLosses() === $other->ranked->getLosses();
    }
}
