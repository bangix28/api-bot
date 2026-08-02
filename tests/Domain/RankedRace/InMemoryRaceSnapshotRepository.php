<?php

namespace App\Tests\Domain\RankedRace;

use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RankedRace\RaceSnapshot;
use App\Domain\RankedRace\RaceSnapshotRepositoryInterface;
use App\Domain\RankedRace\RaceWindow;

final readonly class InMemoryRaceSnapshotRepository implements RaceSnapshotRepositoryInterface
{
    /** @param RaceSnapshot[] $snapshots tous rattachés à la même file dans les tests */
    public function __construct(private array $snapshots = [])
    {
    }

    public function findForWindow(RankedQueueType $queue, RaceWindow $window): array
    {
        return array_values(array_filter(
            $this->snapshots,
            static fn(RaceSnapshot $snapshot) => $snapshot->day >= $window->start && $snapshot->day <= $window->end,
        ));
    }
}
