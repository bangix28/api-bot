<?php

namespace App\Tests\Domain\RiotAccount;

use App\Domain\RiotAccount\MiniSeries;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use PHPUnit\Framework\TestCase;

class RankedQueueEntityTest extends TestCase
{
    public function testFlagsAndMiniSeriesDefaultToAbsent(): void
    {
        $ranked = new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 50, 40, 20);

        $this->assertFalse($ranked->isHotStreak());
        $this->assertFalse($ranked->isVeteran());
        $this->assertFalse($ranked->isFreshBlood());
        $this->assertNull($ranked->getMiniSeries());
    }

    public function testExposesFlagsAndMiniSeries(): void
    {
        $ranked = new RankedQueueEntity(
            RankedRank::I, RankedTier::GOLD, 99, 40, 20,
            hotStreak: true,
            veteran: true,
            freshBlood: true,
            miniSeries: new MiniSeries(2, 1, 3, 'WLW'),
        );

        $this->assertTrue($ranked->isHotStreak());
        $this->assertTrue($ranked->isVeteran());
        $this->assertTrue($ranked->isFreshBlood());
        $this->assertSame(3, $ranked->getMiniSeries()->target);
    }

    public function testRejectsNegativeLeaguePoints(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, -1, 40, 20);
    }

    public function testRejectsMoreThan99LeaguePointsBelowApex(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 150, 40, 20);
    }

    public function testAllowsMoreThan99LeaguePointsForApexTiers(): void
    {
        $ranked = new RankedQueueEntity(RankedRank::I, RankedTier::MASTER, 450, 100, 80);

        $this->assertSame(450, $ranked->getLeaguePoints());
    }

    public function testScoreCombinesTierDivisionAndLeaguePoints(): void
    {
        $ranked = new RankedQueueEntity(RankedRank::II, RankedTier::GOLD, 50, 40, 20);

        $this->assertSame(
            RankedTier::GOLD->getScore() + RankedRank::II->getScore() + 50,
            $ranked->getScore()
        );
    }
}
