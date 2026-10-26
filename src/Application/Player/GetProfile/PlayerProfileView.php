<?php

namespace App\Application\Player\GetProfile;

final readonly class PlayerProfileView
{
    /**
     * @param string[] $riotIds comptes Riot liés au joueur
     */
    public function __construct(
        public string $discordId,
        public string $discordUsername,
        public array $riotIds,
    ) {
    }
}
