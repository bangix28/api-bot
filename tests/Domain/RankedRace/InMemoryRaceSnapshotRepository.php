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
        $inside = array_filter(
            $this->snapshots,
            static fn(RaceSnapshot $s) => $window->contains($s->capturedAt),
        );

        $result = [...array_values($inside), ...$this->carryIn($window)];

        usort(
            $result,
            static fn(RaceSnapshot $a, RaceSnapshot $b) => [$a->player->riotId, $a->capturedAt]
                <=> [$b->player->riotId, $b->capturedAt],
        );

        return $result;
    }

    public function lastCapturedAt(?RankedQueueType $queue = null): ?\DateTimeImmutable
    {
        $latest = null;

        foreach ($this->snapshots as $snapshot) {
            if ($latest === null || $snapshot->capturedAt > $latest) {
                $latest = $snapshot->capturedAt;
            }
        }

        return $latest;
    }

    /**
     * Reproduit le report de l'adaptateur Doctrine : le dernier relevé de
     * chaque joueur avant la fenêtre, dans la limite d'ancienneté admise.
     * Sans cela, les tests d'application valideraient un comportement que la
     * production n'a pas.
     *
     * @return RaceSnapshot[]
     */
    private function carryIn(RaceWindow $window): array
    {
        $latestByPlayer = [];

        foreach ($this->snapshots as $snapshot) {
            if (!$window->isCarryIn($snapshot->capturedAt)) {
                continue;
            }

            $riotId = $snapshot->player->riotId;
            $known = $latestByPlayer[$riotId] ?? null;

            if ($known === null || $snapshot->capturedAt > $known->capturedAt) {
                $latestByPlayer[$riotId] = $snapshot;
            }
        }

        return array_values($latestByPlayer);
    }
}
