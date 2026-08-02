<?php

namespace App\Domain\RankedRace;

use App\Domain\EloSnapshot\RankedQueueType;

/**
 * Événement de course créé en administration (« Sprint de début de saison ») :
 * une fenêtre de dates libre, une file, et un seuil de qualification winrate
 * propre à l'événement (contrairement aux courses calendaires : 5 hebdo / 15 mensuel).
 */
final readonly class RaceEvent
{
    public function __construct(
        public int $id,
        public string $name,
        public RankedQueueType $queue,
        public RaceWindow $window,
        public int $minGamesToQualify,
    ) {
    }

    /** Bornes incluses : l'événement est actif du premier au dernier jour. */
    public function statusAt(\DateTimeImmutable $today): RaceEventStatus
    {
        return match (true) {
            $today < $this->window->start => RaceEventStatus::UPCOMING,
            $today > $this->window->end => RaceEventStatus::FINISHED,
            default => RaceEventStatus::ACTIVE,
        };
    }
}
