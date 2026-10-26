<?php

namespace App\Domain\LanChallenge;

/**
 * Ce que les défis ont besoin de savoir d'une LAN : sa fenêtre et ses participants.
 * Les instants sont des heures civiles (Europe/Paris), comme partout dans le projet.
 */
final readonly class LanSummary
{
    /**
     * @param array<string, string> $participants Riot ID des comptes participants, indexés par puuid
     */
    public function __construct(
        public int $lanId,
        public string $name,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public array $participants,
    ) {
        if ($endsAt <= $startsAt) {
            throw new \InvalidArgumentException('Une LAN doit finir après avoir commencé.');
        }
    }

    public function hasParticipant(string $puuid): bool
    {
        return isset($this->participants[$puuid]);
    }

    /** Fin incluse : jusqu'à l'heure de fin, on peut encore choisir une carte. */
    public function isFinishedAt(\DateTimeImmutable $instant): bool
    {
        return $instant > $this->endsAt;
    }
}
