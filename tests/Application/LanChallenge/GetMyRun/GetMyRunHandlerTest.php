<?php

namespace App\Tests\Application\LanChallenge\GetMyRun;

use App\Application\LanChallenge\GetMyRun\GetMyRunHandler;
use App\Application\LanChallenge\JoinChallenges\JoinChallengesCommand;
use App\Application\LanChallenge\JoinChallenges\JoinChallengesHandler;
use App\Tests\Application\LanChallenge\LanChallengeFixture;
use PHPUnit\Framework\TestCase;

class GetMyRunHandlerTest extends TestCase
{
    public function testAvantDeRejoindreLeParcoursEstVide(): void
    {
        $fixture = new LanChallengeFixture();

        $view = (new GetMyRunHandler($fixture->seats(), $fixture->runs))->handle(LanChallengeFixture::LAN, LanChallengeFixture::ALPHA);

        $this->assertSame('Alpha#EUW', $view->riotId);
        $this->assertSame('LAN d\'automne', $view->lan->name);
        $this->assertNull($view->run);
    }

    public function testApresAvoirRejointLeJoueurVoitSonOffre(): void
    {
        $fixture = new LanChallengeFixture();
        (new JoinChallengesHandler($fixture->seats(), $fixture->runs, $fixture->dealer))
            ->handle(new JoinChallengesCommand(LanChallengeFixture::LAN, LanChallengeFixture::ALPHA));

        $view = (new GetMyRunHandler($fixture->seats(), $fixture->runs))->handle(LanChallengeFixture::LAN, LanChallengeFixture::ALPHA);

        $this->assertSame('puuid-a', $view->run->puuid);
        $this->assertNotNull($view->run->offer);
    }

    public function testChacunNeVoitQueSonParcours(): void
    {
        $fixture = new LanChallengeFixture();
        (new JoinChallengesHandler($fixture->seats(), $fixture->runs, $fixture->dealer))
            ->handle(new JoinChallengesCommand(LanChallengeFixture::LAN, LanChallengeFixture::ALPHA));

        $view = (new GetMyRunHandler($fixture->seats(), $fixture->runs))->handle(LanChallengeFixture::LAN, LanChallengeFixture::BRAVO);

        $this->assertSame('Bravo#EUW', $view->riotId);
        $this->assertNull($view->run, 'Bravo n\'a pas rejoint : il ne voit pas le parcours d\'Alpha.');
    }
}
