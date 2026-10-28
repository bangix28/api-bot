<?php

namespace App\Domain\LanChallenge;

/**
 * Ce que les défis ont besoin de savoir d'une LAN : sa fenêtre, ses participants
 * et les files où ses parties comptent.
 * Les instants sont des heures civiles (Europe/Paris), comme partout dans le projet.
 */
final readonly class LanSummary
{
    /**
     * @param array<string, string> $participants Riot ID des comptes participants, indexés par puuid
     * @param LanQueue[] $acceptedQueues
     */
    public function __construct(
        public int $lanId,
        public string $name,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public array $participants,
        public array $acceptedQueues = [LanQueue::ARAM_MAYHEM],
    ) {
        if ($endsAt <= $startsAt) {
            throw new \InvalidArgumentException('Une LAN doit finir après avoir commencé.');
        }

        if ($acceptedQueues === []) {
            throw new \InvalidArgumentException('Une LAN doit accepter au moins une file.');
        }
    }

    public function accepts(LanQueue $queue): bool
    {
        return in_array($queue, $this->acceptedQueues, true);
    }

    public function hasParticipant(string $puuid): bool
    {
        return isset($this->participants[$puuid]);
    }

    /** Participant désigné par son Riot ID, sans tenir compte de la casse ; null sinon. */
    public function puuidOf(string $riotId): ?string
    {
        foreach ($this->participants as $puuid => $participantRiotId) {
            if (strcasecmp($participantRiotId, trim($riotId)) === 0) {
                return $puuid;
            }
        }

        return null;
    }

    /** Fin incluse : jusqu'à l'heure de fin, on peut encore choisir une carte. */
    public function isFinishedAt(\DateTimeImmutable $instant): bool
    {
        return $instant > $this->endsAt;
    }
}
