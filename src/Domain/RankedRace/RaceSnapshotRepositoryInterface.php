<?php

namespace App\Domain\RankedRace;

use App\Domain\EloSnapshot\RankedQueueType;

/**
 * Port de lecture de la course : les snapshots d'une file sur une fenêtre.
 * Les lignes historiques sans détail de rang (antérieures à la Ranked Race)
 * sont exclues par l'implémentation.
 */
interface RaceSnapshotRepositoryInterface
{
    /** @return RaceSnapshot[] triés par joueur puis par date croissante */
    public function findForWindow(RankedQueueType $queue, RaceWindow $window): array;
}
