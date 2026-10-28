<?php

namespace App\Tests\Domain\LanGame;

use App\Domain\LanChallenge\LanQueue;
use App\Domain\LanGame\LanGame;
use App\Domain\LanGame\LanGameRepositoryInterface;
use App\Domain\LanGame\LanGameStatus;

/** Retrouve la partie d'une session dans le journal des relevés, comme la jointure de l'adaptateur Doctrine. */
final class InMemoryLanGameRepository implements LanGameRepositoryInterface
{
    /** @var array<string, LanGame> */
    private array $games = [];

    public function __construct(private readonly InMemoryAgentReportLog $reportLog)
    {
    }

    public function save(LanGame $game): void
    {
        $this->games[$game->gameId] = $game;
    }

    public function findCandidates(int $lanId, LanQueue $queue, string $playersFingerprint): array
    {
        return array_values(array_filter(
            $this->games,
            static fn(LanGame $game) => $game->lanId === $lanId && $game->queue === $queue && $game->playersFingerprint === $playersFingerprint,
        ));
    }

    public function findBySession(string $tokenDigest, string $sessionId): ?LanGame
    {
        $gameId = $this->reportLog->gameIdOf($tokenDigest, $sessionId);

        return $gameId === null ? null : $this->games[$gameId];
    }

    public function findInProgress(): array
    {
        return array_values(array_filter($this->games, static fn(LanGame $game) => $game->status === LanGameStatus::IN_PROGRESS));
    }

    /** @return LanGame[] */
    public function all(): array
    {
        return array_values($this->games);
    }
}
