<?php

namespace App\Tests\Domain\RankedRace;

use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RankedRace\RaceEvent;
use App\Domain\RankedRace\RaceEventStatus;
use App\Domain\RankedRace\RaceWindow;
use PHPUnit\Framework\TestCase;

class RaceEventTest extends TestCase
{
    public function testStatutCalculeAuxBornesInclusives(): void
    {
        $event = new RaceEvent(
            1,
            'Sprint de début de saison',
            RankedQueueType::SOLO,
            new RaceWindow(new \DateTimeImmutable('2026-08-10'), new \DateTimeImmutable('2026-08-24')),
            10,
        );

        // La veille du début : à venir.
        $this->assertSame(RaceEventStatus::UPCOMING, $event->statusAt(new \DateTimeImmutable('2026-08-09')));
        // Premier et dernier jour inclus : actif.
        $this->assertSame(RaceEventStatus::ACTIVE, $event->statusAt(new \DateTimeImmutable('2026-08-10')));
        $this->assertSame(RaceEventStatus::ACTIVE, $event->statusAt(new \DateTimeImmutable('2026-08-24')));
        // Le lendemain de la fin : terminé.
        $this->assertSame(RaceEventStatus::FINISHED, $event->statusAt(new \DateTimeImmutable('2026-08-25')));
    }
}
