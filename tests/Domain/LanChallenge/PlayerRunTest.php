<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\CardAlreadyLockedException;
use App\Domain\LanChallenge\CardDealer;
use App\Domain\LanChallenge\CardOffer;
use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\InvalidOfferSlotException;
use App\Domain\LanChallenge\NoOfferToChooseFromException;
use App\Domain\LanChallenge\NoRerollLeftException;
use App\Domain\LanChallenge\PlayerRun;
use PHPUnit\Framework\TestCase;

class PlayerRunTest extends TestCase
{
    private static function aRun(): PlayerRun
    {
        return PlayerRun::start(1, 'puuid-1', new CardOffer([Challenge::DOUBLE_KILL, Challenge::TRIPLE_KILL, Challenge::QUADRA_KILL]));
    }

    public function testUnParcoursCommenceAvecUneOffreEtTroisRelances(): void
    {
        $run = self::aRun();

        $this->assertSame(3, $run->rerollsLeft);
        $this->assertSame(0, $run->score);
        $this->assertNotNull($run->offer);
        $this->assertNull($run->activeCard);
    }

    public function testChoisirUneCarteVideLOffre(): void
    {
        $run = self::aRun()->pick(1);

        $this->assertSame(Challenge::TRIPLE_KILL, $run->activeCard);
        $this->assertNull($run->offer);
    }

    public function testLeChoixEstDefinitif(): void
    {
        $this->expectException(NoOfferToChooseFromException::class);

        self::aRun()->pick(0)->pick(1);
    }

    public function testUnEmplacementHorsOffreEstRefuse(): void
    {
        $this->expectException(InvalidOfferSlotException::class);

        self::aRun()->pick(3);
    }

    public function testRelancerConsommeUneRelanceEtRemplaceLaCarte(): void
    {
        $run = self::aRun()->reroll(0, new CardDealer(new ScriptedRandomizer()));

        $this->assertSame(2, $run->rerollsLeft);
        $this->assertNotSame(Challenge::DOUBLE_KILL, $run->offer->cardAt(0));
        $this->assertSame(Challenge::TRIPLE_KILL, $run->offer->cardAt(1));
    }

    public function testAuDelaDeTroisRelancesCEstRefuse(): void
    {
        $dealer = new CardDealer(new ScriptedRandomizer());
        $run = self::aRun()->reroll(0, $dealer)->reroll(0, $dealer)->reroll(0, $dealer);

        $this->expectException(NoRerollLeftException::class);

        $run->reroll(0, $dealer);
    }

    public function testOnNeRelancePlusApresAvoirChoisi(): void
    {
        $this->expectException(NoOfferToChooseFromException::class);

        self::aRun()->pick(0)->reroll(0, new CardDealer(new ScriptedRandomizer()));
    }

    public function testSansCarteChoisieLeLancementNeVerrouilleRien(): void
    {
        // Le joueur joue sans carte et garde son offre pour la partie suivante.
        $run = self::aRun();

        $this->assertSame($run, $run->lockFor('game-1'));
    }

    public function testLeLancementEngageLaCarteSurLaPartie(): void
    {
        $run = self::aRun()->pick(0)->lockFor('game-1');

        $this->assertTrue($run->isLockedOn('game-1'));
        $this->assertSame($run, $run->lockFor('game-1'), 'Re-signaler la même partie ne change rien.');
    }

    public function testUneCarteEngageeNePeutPasPasserAUneAutrePartie(): void
    {
        $this->expectException(CardAlreadyLockedException::class);

        self::aRun()->pick(0)->lockFor('game-1')->lockFor('game-2');
    }
}
