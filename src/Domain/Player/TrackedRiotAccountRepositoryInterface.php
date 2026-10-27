<?php

namespace App\Domain\Player;

interface TrackedRiotAccountRepositoryInterface
{
    /**
     * Compte suivi portant ce Riot ID, sans tenir compte de la casse
     * (« pseudo#euw » et « Pseudo#EUW » désignent le même compte chez Riot).
     */
    public function findByRiotId(RiotId $riotId): ?TrackedRiotAccount;

    /** @return TrackedRiotAccount[] comptes liés à ce joueur Discord */
    public function findOwnedBy(string $discordId): array;

    /** Rattache le compte au joueur, en retenant son nom Discord pour l'administration. */
    public function linkTo(string $puuid, DiscordIdentity $player): void;

    /** @return TrackedRiotAccount[] tous les comptes suivis dont le puuid est connu */
    public function findAllTracked(): array;

    /** Remplace le Riot ID du compte, par exemple après un changement de pseudo chez Riot. */
    public function renameRiotId(string $puuid, RiotId $riotId): void;
}
