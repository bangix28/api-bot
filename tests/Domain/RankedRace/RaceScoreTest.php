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
        // Gold IV 0 LP = 3 paliers de 400 LP franchis.
        // Diamond I 99 LP = 6 x 400 + 3 divisions x 100 + 99, dernier rang avant Master.
        $goldFour = new RankedQueueEntity(RankedRank::IV, RankedTier::GOLD, 0, 0, 0);
        $diamondOne = new RankedQueueEntity(RankedRank::I, RankedTier::DIAMOND, 99, 0, 0);

        $this->assertSame(1200, RaceScore::of($goldFour));
        $this->assertSame(2799, RaceScore::of($diamondOne));
    }

    public function testMasterPlusUtiliseUnPlancherUniqueEtLesLpDirects(): void
    {
        // Règle métier : plancher fixe à Master 0 LP + LP additionnés, sans divisions.
        $master = new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::MASTER, 40, 0, 0);
        $grandmaster = new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::GRANDMASTER, 250, 0, 0);
        $challenger = new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::CHALLENGER, 250, 0, 0);

        $this->assertSame(2840, RaceScore::of($master));
        // GM et Challenger sont des labels cosmétiques : même LP => même score de course.
        $this->assertSame(3050, RaceScore::of($grandmaster));
        $this->assertSame(3050, RaceScore::of($challenger));
    }

    public function testLeScoreDeCourseDiffereDuScoreDuClassementAbsoluEnApex(): void
    {
        // Non-régression : le classement D (rang absolu) garde son échelle GM=9000,
        // qui est persistée en base et sert /api/riot-account et /elo-daily.
        $grandmaster = new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::GRANDMASTER, 250, 0, 0);

        $this->assertSame(9250, $grandmaster->getScore());
        $this->assertSame(3050, RaceScore::of($grandmaster));
    }

    public function testUnePromotionDeTierNeVautQuUnSeulLp(): void
    {
        // Le bug de prod : cette transition valait +601 points pour +1 LP réel.
        $avant = new RankedQueueEntity(RankedRank::I, RankedTier::EMERALD, 99, 0, 0);
        $apres = new RankedQueueEntity(RankedRank::IV, RankedTier::DIAMOND, 0, 0, 0);

        $this->assertSame(1, RaceScore::of($apres) - RaceScore::of($avant));
    }

    public function testUnePromotionVersMasterNeVautQuUnSeulLp(): void
    {
        // Seconde frontière fautive : le plancher apex sautait 900 au lieu de 400,
        // soit 500 points fantômes.
        $avant = new RankedQueueEntity(RankedRank::I, RankedTier::DIAMOND, 99, 0, 0);
        $apres = new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::MASTER, 0, 0, 0);

        $this->assertSame(1, RaceScore::of($apres) - RaceScore::of($avant));
    }

    public function testUneDemotionNeRetireQueLesLpPerdus(): void
    {
        $avant = new RankedQueueEntity(RankedRank::IV, RankedTier::DIAMOND, 0, 0, 0);
        $apres = new RankedQueueEntity(RankedRank::I, RankedTier::EMERALD, 75, 0, 0);

        $this->assertSame(-25, RaceScore::of($apres) - RaceScore::of($avant));
    }

    public function testUnChangementDeDivisionNeVautQuUnSeulLp(): void
    {
        // Non-régression : les divisions étaient déjà continues, elles le restent.
        $avant = new RankedQueueEntity(RankedRank::IV, RankedTier::EMERALD, 99, 0, 0);
        $apres = new RankedQueueEntity(RankedRank::III, RankedTier::EMERALD, 0, 0, 0);

        $this->assertSame(1, RaceScore::of($apres) - RaceScore::of($avant));
    }

    public function testLEchelleAvanceDExactementUnLpDIronIvJusquAMaster(): void
    {
        // L'invariant qui aurait attrapé le bug : balayage exhaustif de tous les
        // rangs, chaque pas devant valoir exactement 1. Un saut de 600 à la
        // frontière de palier n'y survit pas.
        $divisions = [RankedRank::IV, RankedRank::III, RankedRank::II, RankedRank::I];
        $previous = null;
        $positions = 0;

        foreach (RankedTier::cases() as $tier) {
            if ($tier === RankedTier::UNRANKED || $tier->isApex()) {
                continue;
            }

            foreach ($divisions as $division) {
                for ($lp = 0; $lp <= 99; $lp++) {
                    $score = RaceScore::of(new RankedQueueEntity($division, $tier, $lp, 0, 0));

                    if ($previous !== null && $score !== $previous + 1) {
                        $this->fail(sprintf(
                            'Saut de %d à %s %s %d LP : l\'échelle doit avancer de 1 LP à la fois',
                            $score - $previous,
                            $tier->value,
                            $division->value,
                            $lp,
                        ));
                    }

                    $previous = $score;
                    $positions++;
                }
            }
        }

        $master = RaceScore::of(new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::MASTER, 0, 0, 0));

        $this->assertSame(2800, $positions, 'Sept paliers de quatre divisions de 100 LP');
        $this->assertSame(2799, $previous, 'Diamond I 99 LP ferme l\'échelle à divisions');
        $this->assertSame(2800, $master, 'Master 0 LP prolonge Diamond I 99 LP sans discontinuité');
    }

    public function testUnCompteNonClasseNAPasDePlaceDansLEchelle(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RaceScore::of(new RankedQueueEntity(RankedRank::UNRANKED, RankedTier::UNRANKED, 0, 0, 0));
    }
}
