<?php

namespace App\Application\LanChallenge\JoinChallenges;

final readonly class JoinChallengesCommand
{
    public function __construct(
        public int $lanId,
        /** Joueur connecté ; son compte participant est retrouvé par PlayerSeatResolver. */
        public string $discordId,
    ) {
    }
}
