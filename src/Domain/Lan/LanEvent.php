<?php

namespace App\Domain\Lan;

/**
 * Une LAN : une fenêtre de jeu et les comptes qui y participent.
 * Les instants sont des heures civiles (Europe/Paris), comme les courses.
 */
final readonly class LanEvent
{
    /**
     * @param string[] $participantPuuids comptes rafraîchis au moins une fois (puuid connu)
     */
    public function __construct(
        public int $id,
        public string $name,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public array $participantPuuids,
    ) {
        if ($endsAt <= $startsAt) {
            throw new \InvalidArgumentException('Une LAN doit finir après avoir commencé.');
        }
    }

    /** Bornes incluses : une partie terminée pile à l'heure de fin compte encore. */
    public function isInProgressAt(\DateTimeImmutable $instant): bool
    {
        return $instant >= $this->startsAt && $instant <= $this->endsAt;
    }
}
