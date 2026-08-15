<?php

namespace App\Domain\RankedRace;

use App\Domain\EloSnapshot\RankedQueueType;

/**
 * Port de lecture de la course : les snapshots d'une file sur une fenêtre.
 */
interface RaceSnapshotRepositoryInterface
{
    /**
     * Les relevés de la fenêtre, PRÉCÉDÉS pour chaque joueur de son dernier
     * relevé connu avant celle-ci (le « report »).
     *
     * Le report n'est pas un détail d'implémentation : c'est lui qui donne son
     * rang de départ à un joueur inactif en début de période. Sans lui, sa
     * première partie — celle qui fait démarrer sa course — serait invisible,
     * puisque le premier relevé de la fenêtre serait déjà postérieur à elle.
     *
     * Son ancienneté est bornée par RaceWindow::carryInStart().
     *
     * @return RaceSnapshot[] triés par joueur puis par instant croissant
     */
    public function findForWindow(RankedQueueType $queue, RaceWindow $window): array;
}
