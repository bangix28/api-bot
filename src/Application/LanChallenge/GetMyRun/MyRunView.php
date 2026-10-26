<?php

namespace App\Application\LanChallenge\GetMyRun;

use App\Domain\LanChallenge\LanSummary;
use App\Domain\LanChallenge\PlayerRun;

final readonly class MyRunView
{
    /**
     * @param SabotageTarget[] $sabotageTargets joueurs visables, seulement si l'offre contient une carte qui vise un joueur
     */
    public function __construct(
        public LanSummary $lan,
        public string $riotId,
        /** null tant que le joueur n'a pas rejoint les défis. */
        public ?PlayerRun $run,
        public array $sabotageTargets = [],
    ) {
    }
}
