<?php

namespace App\Domain\MatchHistory;

readonly class MatchPerformance
{
    public function __construct(
        public ?float $kda = null,
        public ?float $killParticipation = null,
        public ?float $damagePerMinute = null,
        public ?float $goldPerMinute = null,
        public ?float $visionScorePerMinute = null,
    )
    {
    }

    /**
     * Les "challenges" Riot sont un tableau brut dont les clés peuvent manquer
     * (vieux matchs, modes spéciaux) et dont les valeurs sont parfois des int.
     *
     * @param array<string, mixed> $challenges
     */
    public static function fromChallenges(array $challenges): self
    {
        $float = static fn(string $key): ?float => isset($challenges[$key]) ? (float) $challenges[$key] : null;

        return new self(
            $float('kda'),
            $float('killParticipation'),
            $float('damagePerMinute'),
            $float('goldPerMinute'),
            $float('visionScorePerMinute'),
        );
    }
}
