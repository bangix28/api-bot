<?php

namespace App\Application\LanChallenge\StartGame;

use App\Domain\LanChallenge\PlayerRunRepositoryInterface;

/**
 * Une partie démarre (détectée par Spectator-V5, cf. ADR-0005) : la carte de
 * chaque joueur de la LAN présent est engagée sur cette partie.
 */
final readonly class StartGameHandler
{
    public function __construct(private PlayerRunRepositoryInterface $runs)
    {
    }

    public function handle(StartGameCommand $command): void
    {
        foreach ($command->puuids as $puuid) {
            // Un joueur qui n'a pas rejoint les défis joue simplement sans carte.
            $run = $this->runs->find($command->lanId, $puuid);
            if ($run === null) {
                continue;
            }

            $locked = $run->lockFor($command->gameId);
            if ($locked !== $run) {
                $this->runs->save($locked);
            }
        }
    }
}
