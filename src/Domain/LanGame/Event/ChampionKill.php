<?php

namespace App\Domain\LanGame\Event;

final readonly class ChampionKill implements ObservedEvent
{
    /**
     * @param string[] $assisterNames pseudos des joueurs ayant aidé ; vide pour un solo kill
     */
    public function __construct(
        public string $killerName,
        public string $victimName,
        public array $assisterNames,
    ) {
    }
}
