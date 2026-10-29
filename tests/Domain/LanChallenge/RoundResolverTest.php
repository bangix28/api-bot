<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\LanQueue;
use App\Domain\LanChallenge\CardDealer;
use App\Domain\LanChallenge\CardOffer;
use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\GameReport;
use App\Domain\LanChallenge\PlayerGameStats;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\RoundResolver;
use App\Domain\LanChallenge\Verdict;
use PHPUnit\Framework\TestCase;

class RoundResolverTest extends TestCase
{
    private static function engaged(string $puuid, Challenge $card, string $gameId = 'game-1'): PlayerRun
    {
        $others = array_values(array_filter(
            [Challenge::PRUDENT, Challenge::HUNTER, Challenge::WINGMAN],
            static fn(Challenge $candidate) => $candidate !== $card,
        ));

        return PlayerRun::start(1, $puuid, new CardOffer([$card, $others[0], $others[1]]))
            ->pick(0)
            ->lockFor($gameId);
    }

    private static function engagedWithRerolls(string $puuid, Challenge $card, int $rerollsLeft): PlayerRun
    {
        return PlayerRun::restore(1, $puuid, $rerollsLeft, 0, null, $card, 'game-1', []);
    }

    private static function report(PlayerGameStats ...$players): GameReport
    {
        return new GameReport('game-1', LanQueue::ARAM_MAYHEM, 20 * 60, $players);
    }

    /** @return PlayerRun[] indexés par puuid */
    private static function resolve(GameReport $report, PlayerRun ...$runs): array
    {
        $settled = (new RoundResolver(new CardDealer(new ScriptedRandomizer())))->resolve($report, $runs);

        return array_column(array_map(static fn(PlayerRun $run) => [$run->puuid, $run], $settled), 1, 0);
    }

    public function testUneCarteReussieRapporteSesPoints(): void
    {
        $settled = self::resolve(
            self::report(PlayerGameStatsBuilder::aPlayer('a')->withLargestMultiKill(3)->build()),
            self::engaged('a', Challenge::TRIPLE_KILL),
        );

        $this->assertSame(25, $settled['a']->score);
        $this->assertSame(Verdict::SUCCESS, $settled['a']->results[0]->verdict);
        $this->assertFalse($settled['a']->results[0]->roundBonus, 'Seul dans la partie : jamais de bonus de manche.');
    }

    public function testLaMancheCloseDistribueLOffreSuivante(): void
    {
        $settled = self::resolve(
            self::report(PlayerGameStatsBuilder::aPlayer('a')->build()),
            self::engaged('a', Challenge::DOUBLE_KILL),
        );

        $this->assertNotNull($settled['a']->offer);
        $this->assertNull($settled['a']->activeCard);
        $this->assertFalse($settled['a']->isLockedOn('game-1'));
    }

    public function testTousLesJoueursReussissentDonneLeBonusDeManche(): void
    {
        $settled = self::resolve(
            self::report(
                PlayerGameStatsBuilder::aPlayer('a')->withLargestMultiKill(3)->build(),
                PlayerGameStatsBuilder::aPlayer('b')->withLargestMultiKill(5)->build(),
            ),
            self::engaged('a', Challenge::TRIPLE_KILL),
            self::engaged('b', Challenge::PENTA_KILL),
        );

        // +50 %, arrondi à l'entier inférieur : 25 → 37, 60 → 90.
        $this->assertSame(37, $settled['a']->score);
        $this->assertSame(90, $settled['b']->score);
        $this->assertTrue($settled['a']->results[0]->roundBonus);
    }

    public function testUnEchecFaitTomberLeBonusPourTous(): void
    {
        $settled = self::resolve(
            self::report(
                PlayerGameStatsBuilder::aPlayer('a')->withLargestMultiKill(3)->build(),
                PlayerGameStatsBuilder::aPlayer('b')->withLargestMultiKill(1)->build(),
            ),
            self::engaged('a', Challenge::TRIPLE_KILL),
            self::engaged('b', Challenge::DOUBLE_KILL),
        );

        $this->assertSame(25, $settled['a']->score);
        $this->assertSame(0, $settled['b']->score);
    }

    public function testUnVerdictInconnuNeRapporteRien(): void
    {
        $settled = self::resolve(
            self::report(PlayerGameStatsBuilder::aPlayer('a')->withWin(null)->build()),
            self::engaged('a', Challenge::IMMORTAL),
        );

        $this->assertSame(Verdict::UNKNOWN, $settled['a']->results[0]->verdict);
        $this->assertSame(0, $settled['a']->score);
    }

    public function testChaqueMancheRechargeLesRelancesJusquaTrois(): void
    {
        $settled = self::resolve(
            self::report(
                PlayerGameStatsBuilder::aPlayer('a')->withLargestMultiKill(2)->build(),
                PlayerGameStatsBuilder::aPlayer('b')->withLargestMultiKill(1)->build(),
                PlayerGameStatsBuilder::aPlayer('c')->withWin(null)->build(),
            ),
            self::engagedWithRerolls('a', Challenge::DOUBLE_KILL, 0),
            self::engagedWithRerolls('b', Challenge::DOUBLE_KILL, 2),
            self::engagedWithRerolls('c', Challenge::IMMORTAL, 1),
        );

        $this->assertSame(3, $settled['a']->rerollsLeft, 'Réussite : recharge complète.');
        $this->assertSame(3, $settled['b']->rerollsLeft, 'Échec : recharge plafonnée à 3.');
        $this->assertSame(3, $settled['c']->rerollsLeft, 'Verdict inconnu : recharge aussi.');
    }

    public function testUnStockAuDessusDuPlafondNEstPasRogne(): void
    {
        $settled = self::resolve(
            self::report(PlayerGameStatsBuilder::aPlayer('a')->build()),
            self::engagedWithRerolls('a', Challenge::DOUBLE_KILL, 5),
        );

        $this->assertSame(5, $settled['a']->rerollsLeft);
    }

    public function testUnJoueurAbsentDuReleveEstInconnuPasEchoue(): void
    {
        $settled = self::resolve(self::report(), self::engaged('a', Challenge::DOUBLE_KILL));

        $this->assertSame(Verdict::UNKNOWN, $settled['a']->results[0]->verdict);
    }

    public function testLesParcoursSansCarteSurLaPartieNeSontPasTouches(): void
    {
        $elsewhere = self::engaged('b', Challenge::DOUBLE_KILL, 'game-2');
        $noCard = PlayerRun::start(1, 'c', new CardOffer([Challenge::DOUBLE_KILL, Challenge::PRUDENT, Challenge::HUNTER]));

        $settled = self::resolve(
            self::report(PlayerGameStatsBuilder::aPlayer('a')->withLargestMultiKill(2)->build()),
            self::engaged('a', Challenge::DOUBLE_KILL),
            $elsewhere,
            $noCard,
        );

        $this->assertSame(['a'], array_keys($settled));
    }
}
