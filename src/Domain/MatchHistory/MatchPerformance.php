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
        /** Part des dégâts aux champions de l'équipe, entre 0 et 1. */
        public ?float $teamDamagePercentage = null,
        /** Part des dégâts subis par l'équipe, entre 0 et 1. */
        public ?float $damageTakenOnTeamPercentage = null,
        /** Boules de neige (Marque) qui ont touché un champion. */
        public ?int $snowballsHit = null,
        public ?int $skillshotsDodged = null,
        public ?int $soloKills = null,
        /** Kills réalisés en infériorité numérique. */
        public ?int $outnumberedKills = null,
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
        $int = static fn(string $key): ?int => isset($challenges[$key]) ? (int) $challenges[$key] : null;

        return new self(
            $float('kda'),
            $float('killParticipation'),
            $float('damagePerMinute'),
            $float('goldPerMinute'),
            $float('visionScorePerMinute'),
            $float('teamDamagePercentage'),
            $float('damageTakenOnTeamPercentage'),
            $int('snowballsHit'),
            $int('skillshotsDodged'),
            $int('soloKills'),
            $int('outnumberedKills'),
        );
    }
}
