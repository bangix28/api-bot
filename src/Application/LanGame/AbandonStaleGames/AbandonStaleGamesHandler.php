<?php

namespace App\Application\LanGame\AbandonStaleGames;

use App\Application\LanChallenge\ResolveGame\ResolveGameCommand;
use App\Application\LanChallenge\ResolveGame\ResolveGameHandler;
use App\Domain\LanChallenge\GameReport;
use App\Domain\LanGame\LanGame;
use App\Domain\LanGame\LanGameRepositoryInterface;
use App\Domain\Shared\ClockInterface;

/**
 * Règle en verdict inconnu les parties restées sans relevé pendant une heure
 * (ADR-0005) : agent coupé, PC éteint… Une carte ne doit jamais rester
 * bloquée sur une partie dont on n'aura plus de nouvelles.
 */
final readonly class AbandonStaleGamesHandler
{
    public function __construct(
        private LanGameRepositoryInterface $games,
        private ResolveGameHandler $resolveGame,
        private ClockInterface $clock,
    ) {
    }

    /** @return LanGame[] parties abandonnées */
    public function handle(): array
    {
        $now = $this->clock->now();
        $abandoned = [];

        foreach ($this->games->findInProgress() as $game) {
            if (!$game->isAbandonedAt($now)) {
                continue;
            }

            // Relevé vide : chaque carte engagée sur la partie est jugée « inconnue »
            // et le joueur récupère une relance.
            $this->resolveGame->handle(new ResolveGameCommand($game->lanId, new GameReport($game->gameId, 0, [])));

            $abandonedGame = $game->abandon();
            $this->games->save($abandonedGame);
            $abandoned[] = $abandonedGame;
        }

        return $abandoned;
    }
}
