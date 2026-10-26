<?php

namespace App\Application\LanChallenge\PickCard;

use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\PlayerRunNotFoundException;
use App\Domain\LanChallenge\PlayerRunRepositoryInterface;

final readonly class PickCardHandler
{
    public function __construct(private PlayerRunRepositoryInterface $runs)
    {
    }

    public function handle(PickCardCommand $command): PlayerRun
    {
        $run = $this->runs->find($command->lanId, $command->puuid)
            ?? throw new PlayerRunNotFoundException('Ce joueur n\'a pas rejoint les défis de cette LAN.');

        $run = $run->pick($command->slot);
        $this->runs->save($run);

        return $run;
    }
}
