<?php

namespace App\Application\LanChallenge\PickCard;

use App\Application\LanChallenge\PlayerSeat\PlayerSeatResolver;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\PlayerRunNotFoundException;
use App\Domain\LanChallenge\PlayerRunRepositoryInterface;

final readonly class PickCardHandler
{
    public function __construct(
        private PlayerSeatResolver $seats,
        private PlayerRunRepositoryInterface $runs,
    ) {
    }

    public function handle(PickCardCommand $command): PlayerRun
    {
        $seat = $this->seats->resolveForAction($command->lanId, $command->discordId);

        $run = $this->runs->find($command->lanId, $seat->puuid)
            ?? throw new PlayerRunNotFoundException('Tu n\'as pas encore rejoint les défis de cette LAN.');

        $run = $run->pick($command->slot);
        $this->runs->save($run);

        return $run;
    }
}
