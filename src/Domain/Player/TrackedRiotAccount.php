<?php

namespace App\Domain\Player;

/**
 * Un compte Riot suivi par le site (créé en administration), vu sous l'angle
 * de son propriétaire : à quel joueur Discord appartient-il ?
 */
final readonly class TrackedRiotAccount
{
    public function __construct(
        public string $riotId,
        public string $puuid,
        /** Identifiant Discord du joueur propriétaire, null si le compte n'est lié à personne. */
        public ?string $ownerDiscordId,
    ) {
    }

    public function isOwnedBy(string $discordId): bool
    {
        return $this->ownerDiscordId === $discordId;
    }
}
