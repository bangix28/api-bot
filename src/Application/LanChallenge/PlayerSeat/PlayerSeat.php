<?php

namespace App\Application\LanChallenge\PlayerSeat;

use App\Domain\LanChallenge\LanSummary;

/** La place d'un joueur Discord dans une LAN : le compte Riot avec lequel il y joue. */
final readonly class PlayerSeat
{
    public function __construct(
        public LanSummary $lan,
        public string $puuid,
        public string $riotId,
    ) {
    }
}
