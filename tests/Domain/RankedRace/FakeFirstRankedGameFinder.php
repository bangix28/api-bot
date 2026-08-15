<?php

namespace App\Tests\Domain\RankedRace;

use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RankedRace\FirstRankedGameFinderInterface;
use App\Domain\RankedRace\RaceWindow;
use App\Domain\RankedRace\RankedGameStart;

final class FakeFirstRankedGameFinder implements FirstRankedGameFinderInterface
{
    /** @var array<string, RankedGameStart> indexé par riotId */
    private array $starts;

    /** Files interrogées : vérifie qu'on ne cherche pas une game solo pour la flex. */
    public array $queriedQueues = [];

    /** @param array<string, RankedGameStart> $starts */
    public function __construct(array $starts = [])
    {
        $this->starts = $starts;
    }

    public function findFirst(string $riotId, RankedQueueType $queue, RaceWindow $window): ?RankedGameStart
    {
        $this->queriedQueues[] = $queue->value;

        return $this->starts[$riotId] ?? null;
    }
}
