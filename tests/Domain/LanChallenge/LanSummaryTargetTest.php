<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\LanSummary;
use PHPUnit\Framework\TestCase;

class LanSummaryTargetTest extends TestCase
{
    private static function lan(): LanSummary
    {
        return new LanSummary(1, 'LAN', new \DateTimeImmutable('2026-11-07 14:00'), new \DateTimeImmutable('2026-11-08 04:00'), [
            'puuid-a' => 'Alpha#EUW',
            'puuid-b' => 'Bravo#EUW',
        ]);
    }

    public function testUnParticipantSeRetrouveParSonRiotIdSansTenirCompteDeLaCasse(): void
    {
        $this->assertSame('puuid-b', self::lan()->puuidOf('bravo#euw'));
        $this->assertSame('puuid-b', self::lan()->puuidOf('  Bravo#EUW '));
    }

    public function testUnRiotIdInconnuNeDesignePersonne(): void
    {
        $this->assertNull(self::lan()->puuidOf('Charlie#EUW'));
    }
}
