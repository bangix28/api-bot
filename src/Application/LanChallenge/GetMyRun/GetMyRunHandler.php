<?php

namespace App\Application\LanChallenge\GetMyRun;

use App\Application\LanChallenge\PlayerSeat\PlayerSeatResolver;
use App\Domain\LanChallenge\PlayerRunRepositoryInterface;

/** Le parcours du joueur connecté dans une LAN, y compris après la fin de la LAN. */
final readonly class GetMyRunHandler
{
    public function __construct(
        private PlayerSeatResolver $seats,
        private PlayerRunRepositoryInterface $runs,
    ) {
    }

    public function handle(int $lanId, string $discordId): MyRunView
    {
        $seat = $this->seats->resolve($lanId, $discordId);

        return new MyRunView($seat->lan, $seat->riotId, $this->runs->find($lanId, $seat->puuid));
    }
}
