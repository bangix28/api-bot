<?php

namespace App\Tests\Domain\LanGame;

use App\Domain\LanChallenge\LanQueue;
use App\Domain\LanChallenge\PlayerGameStats;
use App\Domain\LanGame\Event\Ace;
use App\Domain\LanGame\Event\ChampionKill;
use App\Domain\LanGame\Event\FirstBlood;
use App\Domain\LanGame\Event\GameEnd;
use App\Domain\LanGame\Event\Multikill;
use App\Domain\LanGame\GameSnapshot;
use App\Domain\LanGame\GameStatsCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GameStatsCalculatorTest extends TestCase
{
    private const array PARTICIPANTS = ['alpha#euw' => 'puuid-a', 'bravo#euw' => 'puuid-b', 'charlie#euw' => 'puuid-c'];

    private static function statsOf(string $puuid, GameSnapshot $snapshot): PlayerGameStats
    {
        $stats = GameStatsCalculator::report('game-1', LanQueue::ARAM_MAYHEM, $snapshot, self::PARTICIPANTS)->statsFor($puuid);
        self::assertNotNull($stats);

        return $stats;
    }

    public function testLeRapportNeContientQueLesParticipantsDeLaLan(): void
    {
        $report = GameStatsCalculator::report('game-1', LanQueue::ARAM_MAYHEM, GameSnapshotBuilder::aGame()->at(1234.9)->build(), self::PARTICIPANTS);

        $this->assertSame('game-1', $report->gameId);
        $this->assertSame(1234, $report->durationSeconds);
        $this->assertNotNull($report->statsFor('puuid-a'));
        $this->assertNotNull($report->statsFor('puuid-c'));
        $this->assertNull($report->statsFor('puuid-inconnu'));
    }

    public function testUnParticipantAbsentDeLaPartieNeFigurePasAuRapport(): void
    {
        $report = GameStatsCalculator::report('game-1', LanQueue::ARAM_MAYHEM, GameSnapshotBuilder::aGame()->build(), ['alpha#euw' => 'puuid-a']);

        $this->assertNull($report->statsFor('puuid-b'));
    }

    public function testReprendLesScoresDuJoueur(): void
    {
        $stats = self::statsOf('puuid-a', GameSnapshotBuilder::aGame()->withScore('Alpha#EUW', 7, 3, 12)->build());

        $this->assertSame([7, 3, 12], [$stats->kills, $stats->deaths, $stats->assists]);
    }

    /** @return iterable<string, array{int, list<int>, int}> */
    public static function series(): iterable
    {
        yield 'aucun kill' => [0, [], 0];
        yield 'un kill isolé, sans événement Multikill' => [1, [], 1];
        yield 'la plus longue série' => [6, [2, 4, 3], 4];
        yield 'au-delà du penta, plafonnée à 5' => [9, [2, 3, 4, 5, 6], 5];
    }

    /** @param list<int> $streaks */
    #[DataProvider('series')]
    public function testSerieLaPlusLongue(int $kills, array $streaks, int $expected): void
    {
        $snapshot = GameSnapshotBuilder::aGame()
            ->withScore('Alpha#EUW', $kills, 0)
            ->withEvents(...array_map(static fn(int $streak) => new Multikill('Alpha', $streak), $streaks))
            // La série d'un autre joueur ne compte pas.
            ->withEvents(new Multikill('Bravo', 5))
            ->build();

        $this->assertSame($expected, self::statsOf('puuid-a', $snapshot)->largestMultiKill);
    }

    public function testUnSoloKillEstUnKillSansAssistant(): void
    {
        $snapshot = GameSnapshotBuilder::aGame()->withEvents(
            new ChampionKill('Alpha', 'Charlie', []),
            new ChampionKill('Alpha', 'Inconnu4', ['Bravo']),
            new ChampionKill('Alpha', 'Inconnu5', []),
            new ChampionKill('Bravo', 'Inconnu6', []),
        )->build();

        $this->assertSame(2, self::statsOf('puuid-a', $snapshot)->soloKills);
    }

    public function testKillsDEquipeEtAces(): void
    {
        $snapshot = GameSnapshotBuilder::aGame()
            ->withScore('Alpha#EUW', 4, 0)
            ->withScore('Bravo#EUW', 3, 0)
            ->withScore('Charlie#EUW', 5, 0)
            ->withEvents(new Ace('Alpha'), new Ace('Charlie'), new Ace('Alpha'))
            ->build();

        $alpha = self::statsOf('puuid-a', $snapshot);
        $charlie = self::statsOf('puuid-c', $snapshot);

        $this->assertSame(7, $alpha->teamKills);
        $this->assertSame(5, $charlie->teamKills);
        $this->assertSame(2, $alpha->aces);
        $this->assertSame(1, $charlie->aces);
    }

    /** @return iterable<string, array{?bool, ?bool, ?bool}> */
    public static function issues(): iterable
    {
        yield 'victoire du joueur du poste' => [true, true, false];
        yield 'défaite du joueur du poste' => [false, false, true];
        yield 'résultat illisible' => [null, null, null];
    }

    #[DataProvider('issues')]
    public function testLaVictoireEstLueDuPointDeVueDuJoueurDuPoste(?bool $activePlayerWon, ?bool $alphaWon, ?bool $charlieWon): void
    {
        $snapshot = GameSnapshotBuilder::aGame()->withEvents(new GameEnd($activePlayerWon))->build();

        $this->assertSame($alphaWon, self::statsOf('puuid-a', $snapshot)->win);
        $this->assertSame($alphaWon, self::statsOf('puuid-b', $snapshot)->win, 'Même équipe que le joueur du poste.');
        $this->assertSame($charlieWon, self::statsOf('puuid-c', $snapshot)->win);
    }

    public function testLaVictoireVueDepuisLEquipeAdverse(): void
    {
        $snapshot = GameSnapshotBuilder::aGame()->seenBy('Charlie#EUW')->withEvents(new GameEnd(true))->build();

        $this->assertFalse(self::statsOf('puuid-a', $snapshot)->win);
        $this->assertTrue(self::statsOf('puuid-c', $snapshot)->win);
    }

    public function testSansFinDePartieLeVainqueurEstInconnu(): void
    {
        $this->assertNull(self::statsOf('puuid-a', GameSnapshotBuilder::aGame()->build())->win);
    }

    public function testPremierSang(): void
    {
        $snapshot = GameSnapshotBuilder::aGame()
            ->withEvents(new ChampionKill('Bravo', 'Charlie', []), new FirstBlood('Bravo'))
            ->build();

        $this->assertTrue(self::statsOf('puuid-b', $snapshot)->firstBlood);
        $this->assertFalse(self::statsOf('puuid-a', $snapshot)->firstBlood);
    }

    public function testSeulLePremierEvenementPremierSangCompte(): void
    {
        $snapshot = GameSnapshotBuilder::aGame()->withEvents(new FirstBlood('Bravo'), new FirstBlood('Alpha'))->build();

        $this->assertFalse(self::statsOf('puuid-a', $snapshot)->firstBlood);
    }

    public function testSansAucunKillPersonneNAEuLePremierSang(): void
    {
        $this->assertFalse(self::statsOf('puuid-a', GameSnapshotBuilder::aGame()->build())->firstBlood);
    }

    public function testDesKillsSansEvenementPremierSangLaissentLePremierSangInconnu(): void
    {
        $snapshot = GameSnapshotBuilder::aGame()->withEvents(new ChampionKill('Alpha', 'Charlie', []))->build();

        $this->assertNull(self::statsOf('puuid-a', $snapshot)->firstBlood);
    }

    public function testLePlusDeMortsEgaliteComprise(): void
    {
        $snapshot = GameSnapshotBuilder::aGame()
            ->withScore('Alpha#EUW', 0, 9)
            ->withScore('Charlie#EUW', 0, 9)
            ->withScore('Bravo#EUW', 0, 8)
            ->build();

        $this->assertTrue(self::statsOf('puuid-a', $snapshot)->mostDeathsInGame);
        $this->assertTrue(self::statsOf('puuid-c', $snapshot)->mostDeathsInGame);
        $this->assertFalse(self::statsOf('puuid-b', $snapshot)->mostDeathsInGame);
    }

    public function testLeRecordDeMortsPeutEtreDetenuParUnInconnu(): void
    {
        $snapshot = GameSnapshotBuilder::aGame()->withScore('Alpha#EUW', 0, 9)->withScore('Inconnu7#EUW', 0, 10)->build();

        $this->assertFalse(self::statsOf('puuid-a', $snapshot)->mostDeathsInGame);
    }

    public function testDansUnePartieSansMortPersonneNALePlusDeMorts(): void
    {
        $this->assertFalse(self::statsOf('puuid-a', GameSnapshotBuilder::aGame()->build())->mostDeathsInGame);
    }
}
