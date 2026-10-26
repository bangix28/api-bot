<?php

namespace App\Application\LanChallenge\RerollCard;

use App\Application\LanChallenge\PlayerSeat\PlayerSeatResolver;
use App\Domain\LanChallenge\CardDealer;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\PlayerRunNotFoundException;
use App\Domain\LanChallenge\PlayerRunRepositoryInterface;

final readonly class RerollCardHandler
{
    public function __construct(
        private PlayerSeatResolver $seats,
        private PlayerRunRepositoryInterface $runs,
        private CardDealer $dealer,
    ) {
    }

    public function handle(RerollCardCommand $command): PlayerRun
    {
        $seat = $this->seats->resolveForAction($command->lanId, $command->discordId);

        $run = $this->runs->find($command->lanId, $seat->puuid)
            ?? throw new PlayerRunNotFoundException('Tu n\'as pas encore rejoint les défis de cette LAN.');

        $run = $run->reroll($command->slot, $this->dealer);
        $this->runs->save($run);

        return $run;
    }
}
