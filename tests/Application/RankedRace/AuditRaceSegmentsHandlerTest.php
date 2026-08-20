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

    public function testLeCasDeProdNAffichePlusQueLesLpReellementGagnes(): void
    {
        // Non-régression du cas signalé en prod le 2026-08-20. Le joueur a gagné
        // 276 LP (39 en Emerald, 1 pour franchir, 236 en Diamond) et affichait
        // 876 bruts / 1 448,8 pondérés, l'échelle sautant de 600 à la promotion.
        //   Emerald I 60 (2360) -> Emerald I 99 (2399)    -> +39  x1.05 =  40,95
        //   Emerald I 99 (2399) -> Diamond IV 0 (2400)    ->  +1  x1.05 =   1,05
        //   Diamond IV 0 (2400) -> Diamond II 36 (2636)   -> +236 x1.15 = 271,40
        //                                          brut 276      pondéré 313,4
        $audits = $this->handler($this->prodCase())->handle(new AuditRaceSegmentsCommand('solo', 'week'));

        $this->assertCount(1, $audits);
        $audit = $audits[0];

        $this->assertSame('EMERALD I 60 LP', $audit->baselineRank);
        $this->assertSame('DIAMOND II 36 LP', $audit->finishRank);
        $this->assertSame(276, $audit->rawProgression);
        $this->assertSame(313.4, $audit->weightedProgression);
        $this->assertSame(15, $audit->gamesPlayed);
        $this->assertSame(0, $audit->offRaceDelta);
        $this->assertCount(3, $audit->segments);
    }

    public function testLeSegmentDePromotionNeVautPlusQuUnSeulLp(): void
    {
        // Ce segment pesait 601 bruts et 961,6 pondérés — 66 % du score de la
        // semaine — pour une seule partie. Franchir un palier ne rapporte plus
        // que le LP réellement gagné.
        $audits = $this->handler($this->prodCase())->handle(new AuditRaceSegmentsCommand('solo', 'week'));

        $promotion = $audits[0]->segments[1];

        $this->assertSame('EMERALD I 99 LP', $promotion->fromRank);
        $this->assertSame('DIAMOND IV 0 LP', $promotion->toRank);
        $this->assertSame(1, $promotion->games);
        $this->assertSame(1, $promotion->scoreDelta);
        $this->assertSame(1.05, $promotion->weightedDelta);
        $this->assertSame('EMERALD → DIAMOND', $promotion->tierCrossing);
    }

    public function testLeRapportPondereSurBrutMesureEnfinLesLpReels(): void
    {
        // 313,4 / 276 = 1,136 : le rapport est celui d'un joueur Emerald->Diamond
        // aux nouveaux tarifs, et son dénominateur est un vrai nombre de LP. Sur
        // l'ancienne échelle il valait 1,654 — dans la plage, donc muet, alors que
        // le score était faux de 960 points. Le x5,25 signalé en prod venait de la
        // comparaison au LP réel, pas du rapport.
        $audits = $this->handler($this->prodCase())->handle(new AuditRaceSegmentsCommand('solo', 'week'));

        $this->assertSame(1.136, $audits[0]->effectiveCoefficient);
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
        $this->assertSame(67.5, $audits[0]->segments[0]->weightedDelta);
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
