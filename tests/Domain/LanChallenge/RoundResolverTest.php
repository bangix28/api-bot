<?php

namespace App\Tests\Domain\LanChallenge;

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

    private static function report(PlayerGameStats ...$players): GameReport
    {
        return new GameReport('game-1', 20 * 60, $players);
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

        // +50 %, arrondi à l'entier inférieur : 25 → 37, 100 → 150.
        $this->assertSame(37, $settled['a']->score);
        $this->assertSame(150, $settled['b']->score);
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

    public function testUnVerdictInconnuNeRapporteRienMaisRendUneRelance(): void
    {
        $settled = self::resolve(
            self::report(PlayerGameStatsBuilder::aPlayer('a')->withWin(null)->build()),
            self::engaged('a', Challenge::IMMORTAL),
        );

        $this->assertSame(Verdict::UNKNOWN, $settled['a']->results[0]->verdict);
        $this->assertSame(0, $settled['a']->score);
        $this->assertSame(4, $settled['a']->rerollsLeft);
    }

    public function testUnJoueurAbsentDuReleveEstInconnuPasEchoue(): void
    {
        $settled = self::resolve(self::report(), self::engaged('a', Challenge::DOUBLE_KILL));

        $this->assertSame(Verdict::UNKNOWN, $settled['a']->results[0]->verdict);
    }

    public function testEchouerUnPrismatiqueRendUneRelance(): void
    {
        $settled = self::resolve(
            self::report(
                PlayerGameStatsBuilder::aPlayer('a')->withLargestMultiKill(3)->build(),
                PlayerGameStatsBuilder::aPlayer('b')->withLargestMultiKill(1)->build(),
            ),
            self::engaged('a', Challenge::QUADRA_KILL),
            self::engaged('b', Challenge::DOUBLE_KILL),
        );

        $this->assertSame(4, $settled['a']->rerollsLeft);
        $this->assertSame(3, $settled['b']->rerollsLeft, 'Échouer une carte argent ne rend rien.');
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
