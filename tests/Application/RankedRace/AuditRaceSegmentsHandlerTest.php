<?php

namespace App\Tests\Application\RankedRace;

use App\Application\RankedRace\AuditSegments\AuditRaceSegmentsCommand;
use App\Application\RankedRace\AuditSegments\AuditRaceSegmentsHandler;
use App\Domain\RankedRace\InvalidRankedRaceParameterException;
use App\Domain\RankedRace\RacePlayer;
use App\Domain\RankedRace\RaceSnapshot;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Tests\Domain\RankedRace\InMemoryRaceSnapshotRepository;
use App\Tests\Domain\Shared\FixedClock;
use PHPUnit\Framework\TestCase;

class AuditRaceSegmentsHandlerTest extends TestCase
{
    // Vendredi -> fenêtre hebdo du lundi 17 au dimanche 23 août.
    private const string TODAY = '2026-08-21';

    public function testLAuditExhibeLeSegmentDePromotionDuCasDeProd(): void
    {
        // Le cas signalé en prod le 2026-08-20 : 276 LP réellement gagnés
        // (39 en Emerald + 1 pour franchir + 236 en Diamond) affichés à 1 448,8.
        //   Emerald I 60 = 6460, Emerald I 99 = 6499        -> +39  x1.6 =   62,4
        //   Emerald I 99 = 6499, Diamond IV 0 = 7100        -> +601 x1.6 =  961,6
        //   Diamond IV 0 = 7100, Diamond II 36 = 7336       -> +236 x1.8 =  424,8
        //                                            brut 876       pondéré 1 448,8
        $audits = $this->handler($this->prodCase())->handle(new AuditRaceSegmentsCommand('solo', 'week'));

        $this->assertCount(1, $audits);
        $audit = $audits[0];

        $this->assertSame('EMERALD I 60 LP', $audit->baselineRank);
        $this->assertSame('DIAMOND II 36 LP', $audit->finishRank);
        $this->assertSame(876, $audit->rawProgression);
        $this->assertSame(1448.8, $audit->weightedProgression);
        $this->assertSame(15, $audit->gamesPlayed);
        $this->assertSame(0, $audit->offRaceDelta);
        $this->assertCount(3, $audit->segments);
    }

    public function testLeSegmentDePromotionPeseUnePartiePourSixCentsUnPoints(): void
    {
        // Le cœur du diagnostic : une seule partie, +601 bruts, 961,6 pondérés,
        // soit 66 % du score de la semaine. L'échelle saute de 600 à la frontière.
        $audits = $this->handler($this->prodCase())->handle(new AuditRaceSegmentsCommand('solo', 'week'));

        $promotion = $audits[0]->segments[1];

        $this->assertSame('EMERALD I 99 LP', $promotion->fromRank);
        $this->assertSame('DIAMOND IV 0 LP', $promotion->toRank);
        $this->assertSame(1, $promotion->games);
        $this->assertSame(601, $promotion->scoreDelta);
        $this->assertSame(961.6, $promotion->weightedDelta);
        $this->assertSame('EMERALD → DIAMOND', $promotion->tierCrossing);
    }

    public function testLeRapportPondereSurBrutResteDansLaPlageDesCoefficients(): void
    {
        // 1 448,8 / 876 = 1,654 : DANS la plage [1.0 ; 2.2]. C'est ce qui prouve
        // que la pondération n'est pas la cause — elle amplifie une échelle fausse.
        // Le x5,25 constaté vient de la comparaison au LP RÉEL (276), pas au brut.
        $audits = $this->handler($this->prodCase())->handle(new AuditRaceSegmentsCommand('solo', 'week'));

        $this->assertSame(1.654, $audits[0]->effectiveCoefficient);
        $this->assertSame(1, $audits[0]->tierCrossings);
    }

    public function testAucuneFrontiereQuandLeJoueurResteDansSonPalier(): void
    {
        $toto = new RacePlayer('Toto#EUW', 'Toto', '685');
        $repository = new InMemoryRaceSnapshotRepository([
            $this->snapshot($toto, '2026-08-18 20:00', RankedTier::GOLD, RankedRank::III, 20, 10, 10),
            $this->snapshot($toto, '2026-08-18 21:00', RankedTier::GOLD, RankedRank::II, 45, 12, 10),
        ]);

        $audits = $this->handler($repository)->handle(new AuditRaceSegmentsCommand('solo', 'week'));

        $this->assertSame(0, $audits[0]->tierCrossings);
        $this->assertNull($audits[0]->segments[0]->tierCrossing);
        $this->assertSame(125, $audits[0]->rawProgression);
    }

    public function testMasterVersGrandmasterNeFranchitAucuneFrontiere(): void
    {
        // Les trois libellés apex partagent une seule échelle de LP : y passer de
        // l'un à l'autre ne saute rien, et ne doit pas être signalé comme tel.
        $toto = new RacePlayer('Toto#EUW', 'Toto', '685');
        $repository = new InMemoryRaceSnapshotRepository([
            $this->snapshot($toto, '2026-08-18 20:00', RankedTier::MASTER, RankedRank::UNRANKED, 250, 50, 50),
            $this->snapshot($toto, '2026-08-18 21:00', RankedTier::GRANDMASTER, RankedRank::UNRANKED, 300, 51, 50),
        ]);

        $audits = $this->handler($repository)->handle(new AuditRaceSegmentsCommand('solo', 'week'));

        $this->assertSame('MASTER 250 LP', $audits[0]->baselineRank);
        $this->assertSame('GRANDMASTER 300 LP', $audits[0]->finishRank);
        $this->assertSame(0, $audits[0]->tierCrossings);
        $this->assertSame(50, $audits[0]->rawProgression);
        $this->assertSame(110.0, $audits[0]->segments[0]->weightedDelta);
    }

    public function testUnJoueurSansPartieNAAucunSegmentEtAucunRapport(): void
    {
        $toto = new RacePlayer('Toto#EUW', 'Toto', '685');
        $repository = new InMemoryRaceSnapshotRepository([
            $this->snapshot($toto, '2026-08-18 20:00', RankedTier::GOLD, RankedRank::III, 20, 10, 10),
            $this->snapshot($toto, '2026-08-18 21:00', RankedTier::GOLD, RankedRank::III, 20, 10, 10),
        ]);

        $audits = $this->handler($repository)->handle(new AuditRaceSegmentsCommand('solo', 'week'));

        $this->assertSame([], $audits[0]->segments);
        $this->assertSame(0, $audits[0]->rawProgression);
        $this->assertNull($audits[0]->effectiveCoefficient);
    }

    public function testLeFiltreJoueurNeGardeQueLuiMemeAvecPlusieursInscrits(): void
    {
        $toto = new RacePlayer('Toto#EUW', 'Toto', '685');
        $tata = new RacePlayer('Tata#EUW', 'Tata', '12');
        $repository = new InMemoryRaceSnapshotRepository([
            $this->snapshot($toto, '2026-08-18 20:00', RankedTier::GOLD, RankedRank::III, 20, 10, 10),
            $this->snapshot($toto, '2026-08-18 21:00', RankedTier::GOLD, RankedRank::II, 45, 12, 10),
            $this->snapshot($tata, '2026-08-18 20:00', RankedTier::SILVER, RankedRank::IV, 10, 5, 5),
            $this->snapshot($tata, '2026-08-18 21:00', RankedTier::SILVER, RankedRank::IV, 30, 6, 5),
        ]);

        $audits = $this->handler($repository)
            ->handle(new AuditRaceSegmentsCommand('solo', 'week', 'Tata#EUW'));

        $this->assertCount(1, $audits);
        $this->assertSame('Tata#EUW', $audits[0]->riotId);
    }

    public function testFileInvalideRejetee(): void
    {
        $this->expectException(InvalidRankedRaceParameterException::class);

        $this->handler(new InMemoryRaceSnapshotRepository())
            ->handle(new AuditRaceSegmentsCommand('aram', 'week'));
    }

    private function prodCase(): InMemoryRaceSnapshotRepository
    {
        $joueur = new RacePlayer('Kenolane#EUW', 'Kenolane', '685');

        return new InMemoryRaceSnapshotRepository([
            $this->snapshot($joueur, '2026-08-17 20:00', RankedTier::EMERALD, RankedRank::I, 60, 100, 100),
            $this->snapshot($joueur, '2026-08-18 21:30', RankedTier::EMERALD, RankedRank::I, 99, 102, 100),
            $this->snapshot($joueur, '2026-08-18 22:00', RankedTier::DIAMOND, RankedRank::IV, 0, 103, 100),
            $this->snapshot($joueur, '2026-08-21 23:00', RankedTier::DIAMOND, RankedRank::II, 36, 111, 104),
        ]);
    }

    private function handler(InMemoryRaceSnapshotRepository $repository): AuditRaceSegmentsHandler
    {
        return new AuditRaceSegmentsHandler(
            $repository,
            new FixedClock(new \DateTimeImmutable(self::TODAY)),
        );
    }

    private function snapshot(
        RacePlayer $player,
        string $capturedAt,
        RankedTier $tier,
        RankedRank $division,
        int $leaguePoints,
        int $wins,
        int $losses,
    ): RaceSnapshot {
        return new RaceSnapshot(
            $player,
            new \DateTimeImmutable($capturedAt),
            new RankedQueueEntity($division, $tier, $leaguePoints, $wins, $losses),
        );
    }
}
