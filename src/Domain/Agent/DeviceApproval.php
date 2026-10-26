<?php

namespace App\Domain\Agent;

use App\Domain\Player\DiscordIdentity;

/** Qui a approuvé l'appairage, et pour quelle LAN. */
final readonly class DeviceApproval
{
    public function __construct(
        public DiscordIdentity $player,
        public int $lanId,
    ) {
    }
}
