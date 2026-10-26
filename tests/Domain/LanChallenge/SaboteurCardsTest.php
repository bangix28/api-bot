<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\GameReport;
use App\Domain\LanChallenge\PlayerGameStats;
use App\Domain\LanChallenge\Rarity;
use App\Domain\LanChallenge\Sabotage;
use App\Domain\LanChallenge\Verdict;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SaboteurCardsTest extends TestCase
{
    private static function verdict(Challenge $card, PlayerGameStats $stats): Verdict
    {
        return $card->evaluate($stats, new GameReport('game-1', 20 * 60, [$stats]));
    }

    public function testLesQuatreCartesSaboteurSontSecretes(): void
    {
        $saboteurCards = Challenge::ofRarity(Rarity::SABOTEUR);

        $this->assertSame([Challenge::BAD_LOSER, Challenge::MARTYR, Challenge::PICKPOCKET, Challenge::CURSE], $saboteurCards);
        foreach (Challenge::cases() as $card) {
            $this->assertSame(in_array($card, $saboteurCards, true), $card->isSecret(), $card->value);
        }
    }

    public function testSeulesPickpocketEtMaledictionVisentUnJoueur(): void
    {
        $targeting = array_values(array_filter(Challenge::cases(), static fn(Challenge $card) => $card->targetsPlayer()));

        $this->assertSame([Challenge::PICKPOCKET, Challenge::CURSE], $targeting);
    }

    public function testSaboterLaPartieRapporteQuaranteSaboterUnJoueurRien(): void
    {
        $this->assertSame(40, Challenge::BAD_LOSER->points());
        $this->assertSame(40, Challenge::MARTYR->points());
        $this->assertSame(0, Challenge::PICKPOCKET->points());
        $this->assertSame(0, Challenge::CURSE->points());
    }

    public function testLeSaboteurEstLaRareteLaPlusRare(): void
    {
        foreach ([Rarity::SILVER, Rarity::GOLD, Rarity::PRISMATIC] as $rarity) {
            $this->assertLessThan($rarity->drawWeight(), Rarity::SABOTEUR->drawWeight(), $rarity->value);
        }
    }

    /** @return iterable<string, array{Challenge, PlayerGameStats, Verdict}> */
    public static function cases(): iterable
    {
        yield 'mauvais perdant sur une défaite' => [Challenge::BAD_LOSER, PlayerGameStatsBuilder::aPlayer()->withWin(false)->build(), Verdict::SUCCESS];
        yield 'mauvais perdant sur une victoire' => [Challenge::BAD_LOSER, PlayerGameStatsBuilder::aPlayer()->withWin(true)->build(), Verdict::FAILURE];
        yield 'mauvais perdant sans résultat connu' => [Challenge::BAD_LOSER, PlayerGameStatsBuilder::aPlayer()->withWin(null)->build(), Verdict::UNKNOWN];
        yield 'martyr avec le plus de morts' => [Challenge::MARTYR, PlayerGameStatsBuilder::aPlayer()->withMostDeathsInGame(true)->build(), Verdict::SUCCESS];
        yield 'martyr sans le plus de morts' => [Challenge::MARTYR, PlayerGameStatsBuilder::aPlayer()->withMostDeathsInGame(false)->build(), Verdict::FAILURE];
        yield 'martyr non calculé' => [Challenge::MARTYR, PlayerGameStatsBuilder::aPlayer()->withMostDeathsInGame(null)->build(), Verdict::UNKNOWN];
    }

    #[DataProvider('cases')]
    public function testEvaluation(Challenge $card, PlayerGameStats $stats, Verdict $expected): void
    {
        $this->assertSame($expected, self::verdict($card, $stats));
    }

    public function testUneCarteQuiViseUnJoueurNeSeJugePasSurUnMatch(): void
    {
        $this->expectException(\LogicException::class);

        self::verdict(Challenge::PICKPOCKET, PlayerGameStatsBuilder::aPlayer()->build());
    }

    public function testUnSabotageNePeutPorterQueSurUneCarteQuiViseUnJoueur(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Sabotage(Challenge::BAD_LOSER, 'puuid-a', 'puuid-b');
    }

    public function testUnVolDeRelanceSeVoitUneMaledictionNon(): void
    {
        $this->assertTrue((new Sabotage(Challenge::PICKPOCKET, 'puuid-a', 'puuid-b'))->isVisibleToTarget());
        $this->assertFalse((new Sabotage(Challenge::CURSE, 'puuid-a', 'puuid-b'))->isVisibleToTarget());
    }
}
