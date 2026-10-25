<?php

namespace App\Application\LanChallenge\RerollCard;

use App\Domain\LanChallenge\CardDealer;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\PlayerRunNotFoundException;
use App\Domain\LanChallenge\PlayerRunRepositoryInterface;

final readonly class RerollCardHandler
{
    public function __construct(
        private PlayerRunRepositoryInterface $runs,
        private CardDealer $dealer,
    ) {
    }

    public function handle(RerollCardCommand $command): PlayerRun
    {
        $run = $this->runs->find($command->lanId, $command->puuid)
            ?? throw new PlayerRunNotFoundException('Ce joueur n\'a pas rejoint les défis de cette LAN.');

        $run = $run->reroll($command->slot, $this->dealer);
        $this->runs->save($run);

        return $run;
    }
}
