<?php

namespace App\Domain\EloSnapshot;

/**
 * Port appelé par le contexte RiotAccount au moment où il vient de récupérer
 * les rangs auprès de Riot. Enregistrer le point de course à cet instant précis
 * ne coûte aucun appel supplémentaire : la donnée est déjà en mémoire.
 */
interface EloSnapshotRecorderInterface
{
    public function record(string $puuid, RankedQueuesSnapshot $queues, \DateTimeImmutable $observedAt): void;
}
