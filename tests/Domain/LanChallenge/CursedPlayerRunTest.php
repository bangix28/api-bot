<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\CardOffer;
use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\SabotageTargetRequiredException;
use App\Domain\LanChallenge\Verdict;
use PHPUnit\Framework\TestCase;

class CursedPlayerRunTest extends TestCase
{
    private static function nextOffer(): CardOffer
    {
        return new CardOffer([Challenge::DOUBLE_KILL, Challenge::PRUDENT, Challenge::HUNTER]);
    }

    private static function engagedOn(Challenge $card, bool $cursed): PlayerRun
    {
        return PlayerRun::restore(7, 'puuid-a', 3, 0, null, $card, 'game-1', [], $cursed);
    }

    public function testUnJoueurMauditNeGagneQueLaMoitieDeSonProchainDefi(): void
    {
        $run = self::engagedOn(Challenge::TRIPLE_KILL, cursed: true)->settle('game-1', Verdict::SUCCESS, false, self::nextOffer());

        $this->assertSame(12, $run->score, '25 points réduits de moitié, arrondis à l\'entier inférieur.');
        $this->assertTrue($run->results[0]->curseApplied, 'Le résultat révèle la malédiction.');
        $this->assertFalse($run->cursed, 'La malédiction est levée une fois appliquée.');
    }

    public function testLaMaledictionSAppliqueApresLeBonusDeManche(): void
    {
        $run = self::engagedOn(Challenge::TRIPLE_KILL, cursed: true)->settle('game-1', Verdict::SUCCESS, true, self::nextOffer());

        // 25 → 37 avec le bonus, puis 37 → 18 avec la malédiction.
        $this->assertSame(18, $run->score);
        $this->assertTrue($run->results[0]->roundBonus);
    }

    public function testUnEchecNeLevePasLaMalediction(): void
    {
        $run = self::engagedOn(Challenge::TRIPLE_KILL, cursed: true)->settle('game-1', Verdict::FAILURE, false, self::nextOffer());

        $this->assertTrue($run->cursed, 'Elle attend le prochain défi réussi.');
        $this->assertFalse($run->results[0]->curseApplied);
    }

    public function testUnJoueurNonMauditGardeTousSesPoints(): void
    {
        $run = self::engagedOn(Challenge::TRIPLE_KILL, cursed: false)->settle('game-1', Verdict::SUCCESS, false, self::nextOffer());

        $this->assertSame(25, $run->score);
        $this->assertFalse($run->results[0]->curseApplied);
    }

    public function testDeuxMaledictionsNeSeCumulentPas(): void
    {
        $run = PlayerRun::start(7, 'puuid-a', self::nextOffer())->curse()->curse();

        $this->assertTrue($run->cursed);
    }

    public function testUneCarteQuiViseUnJoueurNeSeChoisitPasCommeCarteDeMatch(): void
    {
        $run = PlayerRun::start(7, 'puuid-a', new CardOffer([Challenge::CURSE, Challenge::PRUDENT, Challenge::HUNTER]));

        $this->expectException(SabotageTargetRequiredException::class);

        $run->pick(0);
    }

    public function testMauvaisPerdantSeChoisitCommeUneCarteDeMatch(): void
    {
        $run = PlayerRun::start(7, 'puuid-a', new CardOffer([Challenge::BAD_LOSER, Challenge::PRUDENT, Challenge::HUNTER]))->pick(0);

        $this->assertSame(Challenge::BAD_LOSER, $run->activeCard);
    }

    public function testUnSabotageNePeutPasPorterSurUneCarteDeMatch(): void
    {
        $run = PlayerRun::start(7, 'puuid-a', new CardOffer([Challenge::BAD_LOSER, Challenge::PRUDENT, Challenge::HUNTER]));

        $this->expectException(\LogicException::class);

        $run->afterSabotage(0, self::nextOffer(), 0);
    }
}
