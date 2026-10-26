<?php

namespace App\Application\LanChallenge\JoinChallenges;

final readonly class JoinChallengesCommand
{
    public function __construct(
        public int $lanId,
        public string $puuid,
    ) {
    }
}
