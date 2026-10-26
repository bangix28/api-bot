<?php

namespace App\Application\Agent\ApproveDevice;

use App\Domain\Player\DiscordIdentity;

final readonly class ApproveDeviceCommand
{
    public function __construct(
        /** Joueur connecté sur le front, qui saisit le code. */
        public DiscordIdentity $player,
        /** Code tel que saisi : casse, espaces et tiret indifférents. */
        public string $userCode,
    ) {
    }
}
