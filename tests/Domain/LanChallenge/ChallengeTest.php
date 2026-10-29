<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\GameReport;
use App\Domain\LanChallenge\LanQueue;
use App\Domain\LanChallenge\PlayerGameStats;
use App\Domain\LanChallenge\Rarity;
use App\Domain\LanChallenge\Theme;
use App\Domain\LanChallenge\Verdict;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChallengeTest extends TestCase
{
    private static function verdict(
        Challenge $card,
        PlayerGameStats $stats,
        int $durationSeconds = 20 * 60,
        LanQueue $queue = LanQueue::ARAM_MAYHEM,
    ): Verdict {
        return $card->evaluate($stats, new GameReport('game-1', $queue, $durationSeconds, [$stats]));
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

    public function testLaDescriptionDonneLeSeuilDeChaqueTerrain(): void
    {
        $this->assertSame('Réalise au moins 10 kills (7 sur la Faille).', Challenge::HUNTER->description());
        $this->assertSame('Participe à au moins 60 % des kills de ton équipe (55 % sur la Faille).', Challenge::TEAM_SPIRIT->description());
        $this->assertSame('Gagne une partie de moins de 15 minutes (22 minutes sur la Faille).', Challenge::BLITZ->description());
        $this->assertSame('Réalise au moins 20 assistances avec 3 kills au maximum (12 assistances sur la Faille).', Challenge::ALL_FOR_YOU->description());
    }

    public function testLesCartesLienSontRepartiesDansLesRaretes(): void
    {
        $bond = array_filter(Challenge::cases(), static fn(Challenge $card) => $card->theme() === Theme::BOND);

        $this->assertSame([Rarity::SILVER, Rarity::GOLD, Rarity::GOLD], array_values(array_map(static fn(Challenge $card) => $card->rarity(), $bond)));
        $this->assertNull(Challenge::DOUBLE_KILL->theme());
    }

    public function testLesPointsSuiventLaRareteSaufImmortelEtPenta(): void
    {
        $this->assertSame(10, Challenge::DOUBLE_KILL->points());
        $this->assertSame(25, Challenge::TRIPLE_KILL->points());
        $this->assertSame(60, Challenge::QUADRA_KILL->points());
        $this->assertSame(90, Challenge::PENTA_KILL->points());
        $this->assertSame(100, Challenge::IMMORTAL->points());
    }

    /** @return iterable<string, array{Challenge, PlayerGameStats, Verdict}> */
    public static function cases(): iterable
    {
        $player = static fn() => PlayerGameStatsBuilder::aPlayer();

        yield 'doublé réussi' => [Challenge::DOUBLE_KILL, $player()->withLargestMultiKill(2)->build(), Verdict::SUCCESS];
        yield 'doublé raté' => [Challenge::DOUBLE_KILL, $player()->withLargestMultiKill(1)->build(), Verdict::FAILURE];
        yield 'un penta vaut aussi un doublé' => [Challenge::DOUBLE_KILL, $player()->withLargestMultiKill(5)->build(), Verdict::SUCCESS];
        yield 'prudent à 5 morts' => [Challenge::PRUDENT, $player()->withDeaths(5)->build(), Verdict::SUCCESS];
        yield 'prudent à 6 morts' => [Challenge::PRUDENT, $player()->withDeaths(6)->build(), Verdict::FAILURE];
        yield 'participation 60 %' => [Challenge::TEAM_SPIRIT, $player()->withKills(5)->withAssists(7)->withTeamKills(20)->build(), Verdict::SUCCESS];
        yield 'participation 55 %' => [Challenge::TEAM_SPIRIT, $player()->withKills(5)->withAssists(6)->withTeamKills(20)->build(), Verdict::FAILURE];
        yield 'participation sans kill d\'équipe' => [Challenge::TEAM_SPIRIT, $player()->withTeamKills(0)->build(), Verdict::FAILURE];
        yield 'chasseur' => [Challenge::HUNTER, $player()->withKills(10)->build(), Verdict::SUCCESS];
        yield 'bras droit à 20 assistances' => [Challenge::WINGMAN, $player()->withAssists(20)->build(), Verdict::SUCCESS];
        yield 'bras droit à 19 assistances' => [Challenge::WINGMAN, $player()->withAssists(19)->build(), Verdict::FAILURE];
        yield 'triplé' => [Challenge::TRIPLE_KILL, $player()->withLargestMultiKill(3)->build(), Verdict::SUCCESS];
        yield 'exécuteur sans mort' => [Challenge::EXECUTIONER, $player()->withKills(3)->withAssists(2)->withDeaths(0)->build(), Verdict::SUCCESS];
        yield 'exécuteur KDA 4,5' => [Challenge::EXECUTIONER, $player()->withKills(5)->withAssists(4)->withDeaths(2)->build(), Verdict::FAILURE];
        yield 'duelliste' => [Challenge::DUELIST, $player()->withSoloKills(2)->build(), Verdict::SUCCESS];
        yield 'nettoyeur' => [Challenge::CLEANER, $player()->withAces(1)->build(), Verdict::SUCCESS];
        yield 'premier sang' => [Challenge::FIRST_BLOOD, $player()->withFirstBlood(true)->build(), Verdict::SUCCESS];
        yield 'premier sang inconnu' => [Challenge::FIRST_BLOOD, $player()->withFirstBlood(null)->build(), Verdict::UNKNOWN];
        yield 'intouchable' => [Challenge::UNTOUCHABLE, $player()->withWin(true)->withDeaths(5)->build(), Verdict::SUCCESS];
        yield 'intouchable à 6 morts' => [Challenge::UNTOUCHABLE, $player()->withWin(true)->withDeaths(6)->build(), Verdict::FAILURE];
        yield 'intouchable mais défaite' => [Challenge::UNTOUCHABLE, $player()->withWin(false)->withDeaths(0)->build(), Verdict::FAILURE];
        yield 'intouchable sans résultat connu' => [Challenge::UNTOUCHABLE, $player()->withWin(null)->withDeaths(0)->build(), Verdict::UNKNOWN];
        yield 'quadra' => [Challenge::QUADRA_KILL, $player()->withLargestMultiKill(4)->build(), Verdict::SUCCESS];
        yield 'penta raté sur un quadra' => [Challenge::PENTA_KILL, $player()->withLargestMultiKill(4)->build(), Verdict::FAILURE];
        yield 'immortel' => [Challenge::IMMORTAL, $player()->withWin(true)->withDeaths(0)->build(), Verdict::SUCCESS];
        yield 'immortel avec une mort' => [Challenge::IMMORTAL, $player()->withWin(true)->withDeaths(1)->build(), Verdict::FAILURE];
        yield 'où est maman ? à 12 assistances sur un coéquipier' => [Challenge::WHERE_IS_MAMA, $player()->withMostAssistsOnOneAlly(12)->build(), Verdict::SUCCESS];
        yield 'où est maman ? à 11' => [Challenge::WHERE_IS_MAMA, $player()->withMostAssistsOnOneAlly(11)->build(), Verdict::FAILURE];
        yield 'tout pour toi' => [Challenge::ALL_FOR_YOU, $player()->withAssists(20)->withKills(3)->build(), Verdict::SUCCESS];
        yield 'tout pour toi à 19 assistances' => [Challenge::ALL_FOR_YOU, $player()->withAssists(19)->withKills(0)->build(), Verdict::FAILURE];
        yield 'tout pour toi avec 4 kills' => [Challenge::ALL_FOR_YOU, $player()->withAssists(30)->withKills(4)->build(), Verdict::FAILURE];
        yield 'ange vengeur' => [Challenge::AVENGER, $player()->withAvengedAllies(3)->build(), Verdict::SUCCESS];
        yield 'ange vengeur à 2' => [Challenge::AVENGER, $player()->withAvengedAllies(2)->build(), Verdict::FAILURE];
    }

    #[DataProvider('cases')]
    public function testEvaluation(Challenge $card, PlayerGameStats $stats, Verdict $expected): void
    {
        $this->assertSame($expected, self::verdict($card, $stats));
    }

    /** @return iterable<string, array{Challenge, PlayerGameStats, Verdict}> */
    public static function riftCases(): iterable
    {
        $player = static fn() => PlayerGameStatsBuilder::aPlayer();

        yield 'prudent à 3 morts' => [Challenge::PRUDENT, $player()->withDeaths(3)->build(), Verdict::SUCCESS];
        yield 'prudent à 4 morts' => [Challenge::PRUDENT, $player()->withDeaths(4)->build(), Verdict::FAILURE];
        yield 'participation 55 %' => [Challenge::TEAM_SPIRIT, $player()->withKills(5)->withAssists(6)->withTeamKills(20)->build(), Verdict::SUCCESS];
        yield 'participation 50 %' => [Challenge::TEAM_SPIRIT, $player()->withKills(5)->withAssists(5)->withTeamKills(20)->build(), Verdict::FAILURE];
        yield 'chasseur à 7 kills' => [Challenge::HUNTER, $player()->withKills(7)->build(), Verdict::SUCCESS];
        yield 'chasseur à 6 kills' => [Challenge::HUNTER, $player()->withKills(6)->build(), Verdict::FAILURE];
        yield 'bras droit à 12 assistances' => [Challenge::WINGMAN, $player()->withAssists(12)->build(), Verdict::SUCCESS];
        yield 'bras droit à 11 assistances' => [Challenge::WINGMAN, $player()->withAssists(11)->build(), Verdict::FAILURE];
        yield 'intouchable à 2 morts' => [Challenge::UNTOUCHABLE, $player()->withWin(true)->withDeaths(2)->build(), Verdict::SUCCESS];
        yield 'intouchable à 3 morts' => [Challenge::UNTOUCHABLE, $player()->withWin(true)->withDeaths(3)->build(), Verdict::FAILURE];
        yield 'où est maman ? à 6' => [Challenge::WHERE_IS_MAMA, $player()->withMostAssistsOnOneAlly(6)->build(), Verdict::SUCCESS];
        yield 'où est maman ? à 5' => [Challenge::WHERE_IS_MAMA, $player()->withMostAssistsOnOneAlly(5)->build(), Verdict::FAILURE];
        yield 'tout pour toi à 12 assistances' => [Challenge::ALL_FOR_YOU, $player()->withAssists(12)->withKills(3)->build(), Verdict::SUCCESS];
        yield 'tout pour toi à 11 assistances' => [Challenge::ALL_FOR_YOU, $player()->withAssists(11)->build(), Verdict::FAILURE];
        yield 'ange vengeur à 2' => [Challenge::AVENGER, $player()->withAvengedAllies(2)->build(), Verdict::SUCCESS];
        yield 'ange vengeur à 1' => [Challenge::AVENGER, $player()->withAvengedAllies(1)->build(), Verdict::FAILURE];
        yield 'les seuils fixes ne changent pas' => [Challenge::TRIPLE_KILL, $player()->withLargestMultiKill(3)->build(), Verdict::SUCCESS];
    }

    #[DataProvider('riftCases')]
    public function testEvaluationSurLaFaille(Challenge $card, PlayerGameStats $stats, Verdict $expected): void
    {
        $this->assertSame($expected, self::verdict($card, $stats, 30 * 60, LanQueue::RANKED_SOLO));
        $this->assertSame($expected, self::verdict($card, $stats, 30 * 60, LanQueue::RANKED_FLEX));
    }

    public function testBlitzSurLaFailleSeJugeA22Minutes(): void
    {
        $won = PlayerGameStatsBuilder::aPlayer()->withWin(true)->build();

        $this->assertSame(Verdict::SUCCESS, self::verdict(Challenge::BLITZ, $won, 22 * 60 - 1, LanQueue::RANKED_SOLO));
        $this->assertSame(Verdict::FAILURE, self::verdict(Challenge::BLITZ, $won, 22 * 60, LanQueue::RANKED_SOLO));
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
            foreach (LanQueue::cases() as $queue) {
                if (!$card->targetsPlayer()) {
                    $this->assertNotSame(Verdict::SUCCESS, self::verdict($card, $neutral, queue: $queue), $card->value . ' en ' . $queue->name);
                }
            }
        }
    }
}
