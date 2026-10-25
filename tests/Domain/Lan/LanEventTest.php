<?php

namespace App\Tests\Domain\Lan;

use App\Domain\Lan\LanEvent;
use PHPUnit\Framework\TestCase;

class LanEventTest extends TestCase
{
    private function lan(): LanEvent
    {
        return new LanEvent(
            1,
            'LAN d\'automne',
            new \DateTimeImmutable('2026-11-07 14:00'),
            new \DateTimeImmutable('2026-11-08 04:00'),
            ['puuid-1'],
        );
    }

    public function testEnCoursEntreSesBornesIncluses(): void
    {
        $lan = $this->lan();

        $this->assertTrue($lan->isInProgressAt(new \DateTimeImmutable('2026-11-07 14:00')));
        $this->assertTrue($lan->isInProgressAt(new \DateTimeImmutable('2026-11-07 23:30')));
        $this->assertTrue($lan->isInProgressAt(new \DateTimeImmutable('2026-11-08 04:00')));
    }

    public function testPasEnCoursHorsDeSesBornes(): void
    {
        $lan = $this->lan();

        $this->assertFalse($lan->isInProgressAt(new \DateTimeImmutable('2026-11-07 13:59')));
        $this->assertFalse($lan->isInProgressAt(new \DateTimeImmutable('2026-11-08 04:01')));
    }

    public function testUneLanDoitFinirApresAvoirCommence(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LanEvent(1, 'LAN', new \DateTimeImmutable('2026-11-07 14:00'), new \DateTimeImmutable('2026-11-07 14:00'), []);
    }
}
