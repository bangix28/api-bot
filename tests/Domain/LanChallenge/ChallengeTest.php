<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\GameReport;
use App\Domain\LanChallenge\PlayerGameStats;
use App\Domain\LanChallenge\Rarity;
use App\Domain\LanChallenge\Verdict;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChallengeTest extends TestCase
{
    private static function verdict(Challenge $card, PlayerGameStats $stats, int $durationSeconds = 20 * 60): Verdict
    {
        return $card->evaluate($stats, new GameReport('game-1', $durationSeconds, [$stats]));
    }

    public function testChaqueRareteALieuAUneOffreComplete(): void
    {
        // Une offre tire 3 cartes distinctes : une rareté de moins de 3 cartes
        // forcerait le repli sur tout le catalogue.
        foreach (Rarity::cases() as $rarity) {
            $this->assertGreaterThanOrEqual(3, count(Challenge::ofRarity($rarity)), $rarity->value);
        }
    }

    public function testChaqueCarteALibelleEtDescription(): void
    {
        foreach (Challenge::cases() as $card) {
            $this->assertNotSame('', $card->label(), $card->value);
            $this->assertNotSame('', $card->description(), $card->value);
        }
    }

    public function testLesPointsSuiventLaRareteSaufLePenta(): void
    {
        $this->assertSame(10, Challenge::DOUBLE_KILL->points());
        $this->assertSame(25, Challenge::TRIPLE_KILL->points());
        $this->assertSame(60, Challenge::QUADRA_KILL->points());
        $this->assertSame(100, Challenge::PENTA_KILL->points());
    }

    /** @return iterable<string, array{Challenge, PlayerGameStats, Verdict}> */
    public static function cases(): iterable
    {
        $p = static fn() => PlayerGameStatsBuilder::aPlayer();

        yield 'doublé réussi' => [Challenge::DOUBLE_KILL, $p()->withLargestMultiKill(2)->build(), Verdict::SUCCESS];
        yield 'doublé raté' => [Challenge::DOUBLE_KILL, $p()->withLargestMultiKill(1)->build(), Verdict::FAILURE];
        yield 'un penta vaut aussi un doublé' => [Challenge::DOUBLE_KILL, $p()->withLargestMultiKill(5)->build(), Verdict::SUCCESS];
        yield 'prudent à 5 morts' => [Challenge::PRUDENT, $p()->withDeaths(5)->build(), Verdict::SUCCESS];
        yield 'prudent à 6 morts' => [Challenge::PRUDENT, $p()->withDeaths(6)->build(), Verdict::FAILURE];
        yield 'participation 60 %' => [Challenge::TEAM_SPIRIT, $p()->withKills(5)->withAssists(7)->withTeamKills(20)->build(), Verdict::SUCCESS];
        yield 'participation 55 %' => [Challenge::TEAM_SPIRIT, $p()->withKills(5)->withAssists(6)->withTeamKills(20)->build(), Verdict::FAILURE];
        yield 'participation sans kill d\'équipe' => [Challenge::TEAM_SPIRIT, $p()->withTeamKills(0)->build(), Verdict::FAILURE];
        yield 'chasseur' => [Challenge::HUNTER, $p()->withKills(10)->build(), Verdict::SUCCESS];
        yield 'bras droit' => [Challenge::WINGMAN, $p()->withAssists(19)->build(), Verdict::FAILURE];
        yield 'triplé' => [Challenge::TRIPLE_KILL, $p()->withLargestMultiKill(3)->build(), Verdict::SUCCESS];
        yield 'exécuteur sans mort' => [Challenge::EXECUTIONER, $p()->withKills(3)->withAssists(2)->withDeaths(0)->build(), Verdict::SUCCESS];
        yield 'exécuteur KDA 4,5' => [Challenge::EXECUTIONER, $p()->withKills(5)->withAssists(4)->withDeaths(2)->build(), Verdict::FAILURE];
        yield 'duelliste' => [Challenge::DUELIST, $p()->withSoloKills(2)->build(), Verdict::SUCCESS];
        yield 'nettoyeur' => [Challenge::CLEANER, $p()->withAces(1)->build(), Verdict::SUCCESS];
        yield 'premier sang' => [Challenge::FIRST_BLOOD, $p()->withFirstBlood(true)->build(), Verdict::SUCCESS];
        yield 'premier sang inconnu' => [Challenge::FIRST_BLOOD, $p()->withFirstBlood(null)->build(), Verdict::UNKNOWN];
        yield 'intouchable' => [Challenge::UNTOUCHABLE, $p()->withWin(true)->withDeaths(3)->build(), Verdict::SUCCESS];
        yield 'intouchable mais défaite' => [Challenge::UNTOUCHABLE, $p()->withWin(false)->withDeaths(0)->build(), Verdict::FAILURE];
        yield 'intouchable sans résultat connu' => [Challenge::UNTOUCHABLE, $p()->withWin(null)->withDeaths(0)->build(), Verdict::UNKNOWN];
        yield 'quadra' => [Challenge::QUADRA_KILL, $p()->withLargestMultiKill(4)->build(), Verdict::SUCCESS];
        yield 'penta raté sur un quadra' => [Challenge::PENTA_KILL, $p()->withLargestMultiKill(4)->build(), Verdict::FAILURE];
        yield 'immortel' => [Challenge::IMMORTAL, $p()->withWin(true)->withDeaths(0)->build(), Verdict::SUCCESS];
        yield 'immortel avec une mort' => [Challenge::IMMORTAL, $p()->withWin(true)->withDeaths(1)->build(), Verdict::FAILURE];
    }

    #[DataProvider('cases')]
    public function testEvaluation(Challenge $card, PlayerGameStats $stats, Verdict $expected): void
    {
        $this->assertSame($expected, self::verdict($card, $stats));
    }

    public function testBlitzSeJugeSurLaDureeDeLaPartie(): void
    {
        $won = PlayerGameStatsBuilder::aPlayer()->withWin(true)->build();

        $this->assertSame(Verdict::SUCCESS, self::verdict(Challenge::BLITZ, $won, 15 * 60 - 1));
        $this->assertSame(Verdict::FAILURE, self::verdict(Challenge::BLITZ, $won, 15 * 60));
        $this->assertSame(Verdict::UNKNOWN, self::verdict(Challenge::BLITZ, PlayerGameStatsBuilder::aPlayer()->withWin(null)->build(), 10 * 60));
    }

    public function testLeReleveParDefautNeReussitAucuneCarte(): void
    {
        // Garde-fou du builder : un test qui oublie de régler une statistique
        // ne doit pas réussir une carte par accident.
        $neutral = PlayerGameStatsBuilder::aPlayer()->build();

        foreach (Challenge::cases() as $card) {
            $this->assertSame(Verdict::FAILURE, self::verdict($card, $neutral), $card->value);
        }
    }
}
