<?php

namespace App\Tests\Application\RankedRace;

use App\Application\RankedRace\ListEvents\ListRankedRaceEventsHandler;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RankedRace\RaceEvent;
use App\Domain\RankedRace\RaceWindow;
use App\Tests\Domain\RankedRace\InMemoryRaceEventRepository;
use App\Tests\Domain\Shared\FixedClock;
use PHPUnit\Framework\TestCase;

class ListRankedRaceEventsHandlerTest extends TestCase
{
    public function testListeTousLesEvenementsAvecStatutCalcule(): void
    {
        // Arrange : un passé, un en cours, un futur ; aujourd'hui = 12 août.
        $handler = new ListRankedRaceEventsHandler(
            new InMemoryRaceEventRepository([
                $this->event(1, 'Rush de juillet', '2026-07-01', '2026-07-31', RankedQueueType::SOLO),
                $this->event(2, 'Sprint d\'août', '2026-08-10', '2026-08-24', RankedQueueType::FLEX),
                $this->event(3, 'Marathon de rentrée', '2026-09-01', '2026-09-30', RankedQueueType::SOLO),
            ]),
            new FixedClock(new \DateTimeImmutable('2026-08-12')),
        );

        // Act
        $views = $handler->handle();

        // Assert : tous listés (palmarès consultables), plus récent d'abord, statuts corrects.
        $this->assertCount(3, $views);
        $this->assertSame([3, 2, 1], array_map(static fn($v) => $v->id, $views));
        $this->assertSame(
            ['upcoming', 'active', 'finished'],
            array_map(static fn($v) => $v->status, $views),
        );
        $this->assertSame('flex', $views[1]->queue);
        $this->assertSame('2026-08-10', $views[1]->windowStart);
    }

    private function event(int $id, string $name, string $start, string $end, RankedQueueType $queue): RaceEvent
    {
        return new RaceEvent(
            $id,
            $name,
            $queue,
            new RaceWindow(new \DateTimeImmutable($start), new \DateTimeImmutable($end)),
            5,
        );
    }
}
