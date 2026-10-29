<?php

namespace App\Application\Player;

use App\Domain\Player\PlayerSession;

final readonly class IssuedSession
{
    public function __construct(
        /** Valeur en clair du jeton de rafraîchissement, remise au front cette seule fois. */
        public string $plainRefreshToken,
        public PlayerSession $session,
    ) {
    }
}
