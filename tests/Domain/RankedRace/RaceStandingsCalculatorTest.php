<?php

namespace App\Tests\Domain\RankedRace;

use App\Domain\RankedRace\PlayerRaceSeries;
use App\Domain\RankedRace\RacePlayer;
use App\Domain\RankedRace\RaceSnapshot;
use App\Domain\RankedRace\RaceStandingsCalculator;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use PHPUnit\Framework\TestCase;

class RaceStandingsCalculatorTest extends TestCase
{
    public function testLaPonderationInverseLOrdreQuandLesProgressionsSontProches(): void
    {
        // Iron : +100 LP bruts, x1.0 = 100 pondérés.
        $ironPlayer = $this->series('Iron#EUW', [
            ['2026-08-03', RankedTier::IRON, RankedRank::IV, 0, 10, 10],
            ['2026-08-09', RankedTier::IRON, RankedRank::III, 0, 16, 14],
        ]);
        // Diamond : +90 LP bruts seulement, mais x1.15 = 103,5 pondérés.
        $diamondPlayer = $this->series('Dia#EUW', [
            ['2026-08-03', RankedTier::DIAMOND, RankedRank::IV, 0, 10, 10],
            ['2026-08-09', RankedTier::DIAMOND, RankedRank::IV, 90, 16, 14],
        ]);

        $standings = (new RaceStandingsCalculator())->progressionStandings([$ironPlayer, $diamondPlayer]);

        // Liste triée par progression pondérée : Diamond devant.
        $this->assertSame('Dia#EUW', $standings[0]->series->player()->riotId);
        $this->assertSame(1, $standings[0]->rankWeighted);
        $this->assertSame(2, $standings[0]->rankRaw); // mais 2e en brut

        $this->assertSame('Iron#EUW', $standings[1]->series->player()->riotId);
        $this->assertSame(2, $standings[1]->rankWeighted);
        $this->assertSame(1, $standings[1]->rankRaw);
    }

    public function testLaPonderationNInversePlusUnEcartImportant(): void
    {
        // Le sens de la recalibration : un Diamond assidu ne bat plus un Iron qui
        // a objectivement fait mieux. Avec les tarifs précédents, ces +200 LP en
        // Diamond valaient 360 points et passaient devant les +300 de l'Iron ;
        // le podium était structurellement fermé au bas de l'échelle.
        $ironPlayer = $this->series('Iron#EUW', [
            ['2026-08-03', RankedTier::IRON, RankedRank::IV, 0, 10, 10],
            ['2026-08-09', RankedTier::IRON, RankedRank::I, 0, 20, 15],
        ]);
        $diamondPlayer = $this->series('Dia#EUW', [
            ['2026-08-03', RankedTier::DIAMOND, RankedRank::IV, 0, 10, 10],
            ['2026-08-09', RankedTier::DIAMOND, RankedRank::II, 0, 20, 15],
        ]);

        $standings = (new RaceStandingsCalculator())->progressionStandings([$ironPlayer, $diamondPlayer]);

        // 300 pondérés contre 200 x 1.15 = 230 : l'Iron tient les deux classements.
        $this->assertSame('Iron#EUW', $standings[0]->series->player()->riotId);
        $this->assertSame(1, $standings[0]->rankWeighted);
        $this->assertSame(1, $standings[0]->rankRaw);
    }

    public function testEgaliteDeProgressionDepartageeParMoinsDePartiesPuisWinrate(): void
    {
        // Même delta (+100 en Silver), mais efficacités différentes.
        $efficient = $this->series('Efficace#EUW', [
            ['2026-08-03', RankedTier::SILVER, RankedRank::II, 0, 10, 10],
            ['2026-08-09', RankedTier::SILVER, RankedRank::I, 0, 14, 11],  // 5 parties
        ]);
        $grinder = $this->series('Grinder#EUW', [
            ['2026-08-03', RankedTier::SILVER, RankedRank::II, 0, 10, 10],
            ['2026-08-09', RankedTier::SILVER, RankedRank::I, 0, 20, 15],  // 15 parties
        ]);

        $standings = (new RaceStandingsCalculator())->progressionStandings([$grinder, $efficient]);

        $this->assertSame('Efficace#EUW', $standings[0]->series->player()->riotId);
        $this->assertSame('Grinder#EUW', $standings[1]->series->player()->riotId);
    }

    public function testWinrateSepareQualifiesEtNonQualifies(): void
    {
        $qualified = $this->series('Qualifie#EUW', [
            ['2026-08-03', RankedTier::GOLD, RankedRank::II, 0, 10, 10],
            ['2026-08-09', RankedTier::GOLD, RankedRank::I, 0, 14, 12],   // 6 parties, 66.7%
        ]);
        $almostThere = $this->series('Presque#EUW', [
            ['2026-08-03', RankedTier::GOLD, RankedRank::II, 0, 10, 10],
            ['2026-08-09', RankedTier::GOLD, RankedRank::II, 50, 12, 11], // 3 parties -> non qualifié en hebdo
        ]);

        $standings = (new RaceStandingsCalculator())->winrateStandings([$almostThere, $qualified], 5);

        $this->assertCount(1, $standings->qualified);
        $this->assertSame('Qualifie#EUW', $standings->qualified[0]->player()->riotId);

        $this->assertCount(1, $standings->notQualified);
        $this->assertSame('Presque#EUW', $standings->notQualified[0]->player()->riotId);
        $this->assertSame(3, $standings->notQualified[0]->gamesPlayed()); // le compteur « 3/5 parties »
    }

    public function testEgaliteDeWinrateDepartageeParVictoiresPuisParties(): void
    {
        // Tous à 60% : départage par le nombre de victoires.
        $sixWins = $this->series('SixWins#EUW', [
            ['2026-08-03', RankedTier::GOLD, RankedRank::II, 0, 10, 10],
            ['2026-08-09', RankedTier::GOLD, RankedRank::I, 0, 16, 14],   // 10 parties, 6 wins
        ]);
        $threeWins = $this->series('TroisWins#EUW', [
            ['2026-08-03', RankedTier::GOLD, RankedRank::II, 0, 10, 10],
            ['2026-08-09', RankedTier::GOLD, RankedRank::I, 0, 13, 12],   // 5 parties, 3 wins
        ]);

        $standings = (new RaceStandingsCalculator())->winrateStandings([$threeWins, $sixWins], 5);

        $this->assertSame('SixWins#EUW', $standings->qualified[0]->player()->riotId);
        $this->assertSame('TroisWins#EUW', $standings->qualified[1]->player()->riotId);
    }

    /** @param array<array{0: string, 1: RankedTier, 2: RankedRank, 3: int, 4: int, 5: int}> $rows */
    private function series(string $riotId, array $rows): PlayerRaceSeries
    {
        $player = new RacePlayer($riotId, explode('#', $riotId)[0], '1');

        $snapshots = array_map(
            static fn(array $row) => new RaceSnapshot(
                $player,
                new \DateTimeImmutable($row[0]),
                new RankedQueueEntity($row[2], $row[1], $row[3], $row[4], $row[5]),
            ),
            $rows,
        );

        return new PlayerRaceSeries($player, $snapshots);
    }
}
