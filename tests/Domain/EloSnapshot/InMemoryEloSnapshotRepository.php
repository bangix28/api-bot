<?php

namespace App\Tests\Domain\EloSnapshot;

use App\Domain\EloSnapshot\DailyEloSnapshot;
use App\Domain\EloSnapshot\EloSnapshotRepositoryInterface;

class InMemoryEloSnapshotRepository implements EloSnapshotRepositoryInterface
{
    /** @var DailyEloSnapshot[] */
    private array $snapshots = [];

    /** @param DailyEloSnapshot[] $snapshots */
    public function __construct(array $snapshots = [])
    {
        $this->snapshots = $snapshots;
    }

    public function existsFor(string $puuid, \DateTimeImmutable $day): bool
    {
        foreach ($this->snapshots as $snapshot) {
            if ($snapshot->puuid === $puuid && $snapshot->day->format('Y-m-d') === $day->format('Y-m-d')) {
                return true;
            }
        }

        return false;
    }

    public function add(DailyEloSnapshot $snapshot): void
    {
        $this->snapshots[] = $snapshot;
    }

    /** @return DailyEloSnapshot[] */
    public function all(): array
    {
        return $this->snapshots;
    }
}
