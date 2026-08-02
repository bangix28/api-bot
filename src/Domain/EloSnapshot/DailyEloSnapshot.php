<?php

namespace App\Domain\EloSnapshot;

use App\Domain\RiotAccount\RankedQueueEntity;

/**
 * Photo du rang d'un compte dans une file, un jour donné.
 * Réutilise RankedQueueEntity : validation (LP, apex) et score composite.
 */
readonly class DailyEloSnapshot
{
    public function __construct(
        public string $puuid,
        public \DateTimeImmutable $day,
        public RankedQueueType $queue,
        public RankedQueueEntity $ranked,
    ) {
    }
}
