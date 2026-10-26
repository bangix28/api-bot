<?php

namespace App\Domain\LanChallenge;

/**
 * Relevé d'une partie terminée : sa durée et les statistiques des joueurs suivis.
 */
final readonly class GameReport
{
    /** @var array<string, PlayerGameStats> indexé par puuid */
    public array $players;

    /**
     * @param PlayerGameStats[] $players
     */
    public function __construct(
        public string $gameId,
        public int $durationSeconds,
        array $players,
    ) {
        if ($durationSeconds < 0) {
            throw new \InvalidArgumentException('La durée d\'une partie ne peut pas être négative.');
        }

        $indexed = [];
        foreach ($players as $stats) {
            $indexed[$stats->puuid] = $stats;
        }
        $this->players = $indexed;
    }

    public function statsFor(string $puuid): ?PlayerGameStats
    {
        return $this->players[$puuid] ?? null;
    }
}
