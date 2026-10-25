<?php

namespace App\Application\LanChallenge\StartGame;

final readonly class StartGameCommand
{
    /**
     * @param string[] $puuids joueurs de la LAN présents dans la partie
     */
    public function __construct(
        public int $lanId,
        public string $gameId,
        public array $puuids,
    ) {
    }
}
