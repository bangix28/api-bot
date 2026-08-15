<?php

namespace App\Tests\Domain\EloSnapshot;

use App\Domain\EloSnapshot\EloSnapshot;
use App\Domain\EloSnapshot\EloSnapshotWriteRepositoryInterface;
use App\Domain\EloSnapshot\RankedQueueType;

final class InMemoryEloSnapshotWriteRepository implements EloSnapshotWriteRepositoryInterface
{
    /** @var EloSnapshot[] */
    private array $snapshots = [];

    /** @param EloSnapshot[] $snapshots */
    public function __construct(array $snapshots = [])
    {
        foreach ($snapshots as $snapshot) {
            $this->snapshots[] = $snapshot;
        }
    }

    public function lastFor(string $puuid, RankedQueueType $queue): ?EloSnapshot
    {
        $matching = array_filter(
            $this->snapshots,
            static fn(EloSnapshot $s) => $s->puuid === $puuid && $s->queue === $queue,
        );

        if ($matching === []) {
            return null;
        }

        usort($matching, static fn(EloSnapshot $a, EloSnapshot $b) => $a->capturedAt <=> $b->capturedAt);

        return $matching[count($matching) - 1];
    }

    public function add(EloSnapshot $snapshot): void
    {
        $this->snapshots[] = $snapshot;
    }

    /** @return EloSnapshot[] */
    public function all(): array
    {
        return $this->snapshots;
    }

    /** @return EloSnapshot[] */
    public function forQueue(RankedQueueType $queue): array
    {
        return array_values(array_filter($this->snapshots, static fn(EloSnapshot $s) => $s->queue === $queue));
    }
}
