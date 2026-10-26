<?php

namespace App\Application\Player\LinkRiotAccount;

use App\Domain\Player\DiscordIdentity;

final readonly class LinkRiotAccountCommand
{
    public function __construct(
        /** Joueur connecté, tel que son jeton de session l'identifie. */
        public DiscordIdentity $player,
        /** Riot ID saisi par le joueur, « Pseudo#TAG ». */
        public string $riotId,
    ) {
    }
}
