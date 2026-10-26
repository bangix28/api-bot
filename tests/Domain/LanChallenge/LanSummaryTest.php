<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\LanSummary;
use PHPUnit\Framework\TestCase;

class LanSummaryTest extends TestCase
{
    private static function lanEndingAt(string $endsAt): LanSummary
    {
        return new LanSummary(1, 'LAN', new \DateTimeImmutable('2026-11-07 14:00'), new \DateTimeImmutable($endsAt), ['puuid-a' => 'Alpha#EUW']);
    }

    public function testUneLanDoitFinirApresAvoirCommence(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::lanEndingAt('2026-11-07 14:00');
    }

    public function testLaLanEstTermineeApresSonHeureDeFin(): void
    {
        $lan = self::lanEndingAt('2026-11-08 04:00');

        $this->assertFalse($lan->isFinishedAt(new \DateTimeImmutable('2026-11-08 04:00')));
        $this->assertTrue($lan->isFinishedAt(new \DateTimeImmutable('2026-11-08 04:00:01')));
    }

    public function testSeulsLesComptesInscritsParticipent(): void
    {
        $lan = self::lanEndingAt('2026-11-08 04:00');

        $this->assertTrue($lan->hasParticipant('puuid-a'));
        $this->assertFalse($lan->hasParticipant('puuid-z'));
    }
}
