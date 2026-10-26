<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\CardDealer;
use App\Domain\LanChallenge\CardOffer;
use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\Rarity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CardDealerTest extends TestCase
{
    /** @return iterable<string, array{int, Rarity}> */
    public static function rolls(): iterable
    {
        yield '1 → argent' => [1, Rarity::SILVER];
        yield '65 → argent' => [65, Rarity::SILVER];
        yield '66 → or' => [66, Rarity::GOLD];
        yield '93 → or' => [93, Rarity::GOLD];
        yield '94 → prismatique' => [94, Rarity::PRISMATIC];
        yield '100 → prismatique' => [100, Rarity::PRISMATIC];
    }

    #[DataProvider('rolls')]
    public function testLaRareteSuitLesPoids(int $roll, Rarity $expected): void
    {
        // Chaque carte consomme deux tirages : la rareté, puis la carte.
        $dealer = new CardDealer(new ScriptedRandomizer([$roll, 0, $roll, 0, $roll, 0]));

        foreach ($dealer->dealOffer()->cards as $card) {
            $this->assertSame($expected, $card->rarity());
        }
    }

    public function testLesPoidsDeRareteFontCent(): void
    {
        $this->assertSame(100, array_sum(array_map(static fn(Rarity $rarity) => $rarity->drawWeight(), Rarity::cases())));
    }

    public function testUneOffreNeContientJamaisDeuxFoisLaMemeCarte(): void
    {
        // Même tirage à chaque fois (argent, première carte) : sans exclusion,
        // l'offre serait trois fois la même carte.
        $offer = (new CardDealer(new ScriptedRandomizer()))->dealOffer();

        $this->assertSame([Challenge::DOUBLE_KILL, Challenge::PRUDENT, Challenge::TEAM_SPIRIT], $offer->cards);
    }

    public function testRelancerNeRendJamaisUneCarteDejaProposee(): void
    {
        $offer = new CardOffer([Challenge::DOUBLE_KILL, Challenge::PRUDENT, Challenge::TEAM_SPIRIT]);

        $rerolled = (new CardDealer(new ScriptedRandomizer()))->reroll($offer, 0);

        // Première carte argent hors offre : ni la carte refusée, ni les deux gardées.
        $this->assertSame([Challenge::HUNTER, Challenge::PRUDENT, Challenge::TEAM_SPIRIT], $rerolled->cards);
    }
}
