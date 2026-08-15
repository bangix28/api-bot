<?php

namespace App\Application\EloSnapshot\RecordEloSnapshot;

use App\Domain\EloSnapshot\EloChangeDetector;
use App\Domain\EloSnapshot\EloSnapshot;
use App\Domain\EloSnapshot\EloSnapshotRecorderInterface;
use App\Domain\EloSnapshot\EloSnapshotWriteRepositoryInterface;
use App\Domain\EloSnapshot\RankedQueuesSnapshot;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RiotAccount\RankedQueueEntity;

/**
 * Enregistre le point de course d'un compte à partir de rangs déjà récupérés.
 * N'appelle jamais Riot : c'est tout l'intérêt du branchement sur le refresh
 * existant.
 */
final readonly class RecordEloSnapshotHandler implements EloSnapshotRecorderInterface
{
    public function __construct(private EloSnapshotWriteRepositoryInterface $snapshots)
    {
    }

    public function record(string $puuid, RankedQueuesSnapshot $queues, \DateTimeImmutable $observedAt): void
    {
        foreach ([[RankedQueueType::SOLO, $queues->solo], [RankedQueueType::FLEX, $queues->flex]] as [$queue, $ranked]) {
            $this->recordQueue($puuid, $queue, $ranked, $observedAt);
        }
    }

    private function recordQueue(
        string $puuid,
        RankedQueueType $queue,
        ?RankedQueueEntity $ranked,
        \DateTimeImmutable $observedAt,
    ): void {
        if (!($ranked?->isRanked() ?? false)) {
            return;
        }

        $candidate = new EloSnapshot($puuid, $observedAt, $queue, $ranked);

        if (!EloChangeDetector::shouldRecord($this->snapshots->lastFor($puuid, $queue), $candidate)) {
            return;
        }

        $this->snapshots->add($candidate);
    }
}
