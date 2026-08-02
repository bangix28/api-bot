<?php

namespace App\Tests\Domain\RankedRace;

use App\Domain\RankedRace\RaceScore;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use PHPUnit\Framework\TestCase;

class RaceScoreTest extends TestCase
{
    public function testScoreClassiquePourLesTiersAvecDivisions(): void
    {
        $goldFour = new RankedQueueEntity(RankedRank::IV, RankedTier::GOLD, 0, 0, 0);
        $diamondOne = new RankedQueueEntity(RankedRank::I, RankedTier::DIAMOND, 99, 0, 0);

        $this->assertSame(4100, RaceScore::of($goldFour));
        $this->assertSame(7499, RaceScore::of($diamondOne));
    }

    public function testMasterPlusUtiliseUnPlancherUniqueEtLesLpDirects(): void
    {
        // Règle métier : plancher fixe à Master 0 LP + LP additionnés, sans divisions.
        $master = new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::MASTER, 40, 0, 0);
        $grandmaster = new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::GRANDMASTER, 250, 0, 0);
        $challenger = new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::CHALLENGER, 250, 0, 0);

        $this->assertSame(8040, RaceScore::of($master));
        // GM et Challenger sont des labels cosmétiques : même LP => même score de course.
        $this->assertSame(8250, RaceScore::of($grandmaster));
        $this->assertSame(8250, RaceScore::of($challenger));
    }

    public function testLeScoreDeCourseDiffereDuScoreDuClassementAbsoluEnApex(): void
    {
        // Non-régression : le classement D (rang absolu) garde son échelle GM=9000.
        $grandmaster = new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::GRANDMASTER, 250, 0, 0);

        $this->assertSame(9250, $grandmaster->getScore());
        $this->assertSame(8250, RaceScore::of($grandmaster));
    }
}
