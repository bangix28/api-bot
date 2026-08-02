<?php

namespace App\Domain\EloSnapshot;

use App\Domain\RiotAccount\RankedQueueEntity;

/**
 * Rangs d'un compte dans les deux files classées, tels que renvoyés par Riot.
 * null = non classé dans cette file (l'absence est une info, pas un UNRANKED synthétique).
 */
readonly class RankedQueuesSnapshot
{
    public function __construct(
        public ?RankedQueueEntity $solo,
        public ?RankedQueueEntity $flex,
    ) {
    }
}
