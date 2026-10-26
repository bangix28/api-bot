<?php

namespace App\Tests\Application\LanChallenge\PickCard;

use App\Application\LanChallenge\GetMyRun\GetMyRunHandler;
use App\Application\LanChallenge\GetMyRun\SabotageTarget;
use App\Application\LanChallenge\GetStandings\GetStandingsHandler;
use App\Application\LanChallenge\GetStandings\StandingEntry;
use App\Application\LanChallenge\PickCard\PickCardCommand;
use App\Domain\LanChallenge\CardOffer;
use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\InvalidSabotageTargetException;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\SabotageTargetNotAllowedException;
use App\Domain\LanChallenge\SabotageTargetRequiredException;
use App\Tests\Application\LanChallenge\LanChallengeFixture;
use PHPUnit\Framework\TestCase;

class SabotageFlowTest extends TestCase
{
    private LanChallengeFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new LanChallengeFixture();
        // Alpha tient une offre avec les deux cartes qui visent un joueur ; Bravo
        // a rejoint avec 2 relances ; Charlie n'a pas rejoint.
        $this->fixture->runs->save(PlayerRun::restore(
            LanChallengeFixture::LAN, 'puuid-a', 1, 0,
            new CardOffer([Challenge::PICKPOCKET, Challenge::CURSE, Challenge::BAD_LOSER]), null, null, [],
        ));
        $this->fixture->runs->save(PlayerRun::restore(
            LanChallengeFixture::LAN, 'puuid-b', 2, 0,
            new CardOffer([Challenge::DOUBLE_KILL, Challenge::PRUDENT, Challenge::HUNTER]), null, null, [],
        ));
    }

    private function pick(int $slot, ?string $target = null): PlayerRun
    {
        return $this->fixture->pickCardHandler()->handle(new PickCardCommand(LanChallengeFixture::LAN, LanChallengeFixture::ALPHA, $slot, $target));
    }

    public function testPickpocketSurUnParticipantInscrit(): void
    {
        $saboteur = $this->pick(0, 'bravo#euw');

        $this->assertSame(2, $saboteur->rerollsLeft);
        $this->assertSame(1, $this->fixture->runs->find(LanChallengeFixture::LAN, 'puuid-b')->rerollsLeft);
        $this->assertSame(2, $this->fixture->runs->find(LanChallengeFixture::LAN, 'puuid-a')->rerollsLeft, 'Le saboteur est enregistré.');
        $this->assertCount(1, $this->fixture->sabotageLog->recorded[LanChallengeFixture::LAN]);
        $this->assertSame('puuid-b', $this->fixture->sabotageLog->recorded[LanChallengeFixture::LAN][0]->targetPuuid);
    }

    public function testMaledictionSurUnParticipantInscrit(): void
    {
        $this->pick(1, 'Bravo#EUW');

        $this->assertTrue($this->fixture->runs->find(LanChallengeFixture::LAN, 'puuid-b')->cursed);
        $this->assertNotNull($this->fixture->runs->find(LanChallengeFixture::LAN, 'puuid-a')->offer, 'Le saboteur a une offre neuve.');
    }

    public function testUneCarteQuiViseUnJoueurExigeUneCible(): void
    {
        $this->expectException(SabotageTargetRequiredException::class);

        $this->pick(0);
    }

    public function testUneCarteDeMatchRefuseUneCible(): void
    {
        $this->expectException(SabotageTargetNotAllowedException::class);

        $this->pick(2, 'Bravo#EUW');
    }

    public function testUneCarteDeMatchSansCibleSeChoisitNormalement(): void
    {
        $this->assertSame(Challenge::BAD_LOSER, $this->pick(2)->activeCard);
        $this->assertSame([], $this->fixture->sabotageLog->recorded);
    }

    public function testUneCibleHorsLanEstRefusee(): void
    {
        $this->expectException(InvalidSabotageTargetException::class);

        $this->pick(0, 'Inconnu#EUW');
    }

    public function testUneCibleQuiNAPasRejointEstRefusee(): void
    {
        $this->expectException(InvalidSabotageTargetException::class);

        $this->pick(0, 'Charlie#EUW');
    }

    public function testLesCiblesPossiblesSontEnvoyeesAvecLOffre(): void
    {
        $view = (new GetMyRunHandler($this->fixture->seats(), $this->fixture->runs))->handle(LanChallengeFixture::LAN, LanChallengeFixture::ALPHA);

        // Inscrits aux défis, sauf soi-même : Charlie n'a pas rejoint.
        $this->assertEquals([new SabotageTarget('Bravo#EUW', true)], $view->sabotageTargets);
    }

    public function testUneCibleSansRelanceEstSignaleePourLePickpocket(): void
    {
        $this->fixture->runs->save(PlayerRun::restore(
            LanChallengeFixture::LAN, 'puuid-b', 0, 0,
            new CardOffer([Challenge::DOUBLE_KILL, Challenge::PRUDENT, Challenge::HUNTER]), null, null, [],
        ));

        $view = (new GetMyRunHandler($this->fixture->seats(), $this->fixture->runs))->handle(LanChallengeFixture::LAN, LanChallengeFixture::ALPHA);

        $this->assertFalse($view->sabotageTargets[0]->canBePickpocketed);
    }

    public function testSansCarteQuiViseUnJoueurAucuneCibleNEstEnvoyee(): void
    {
        $view = (new GetMyRunHandler($this->fixture->seats(), $this->fixture->runs))->handle(LanChallengeFixture::LAN, LanChallengeFixture::BRAVO);

        $this->assertSame([], $view->sabotageTargets);
    }

    public function testLaMaledictionResteInvisibleDansLeClassement(): void
    {
        $this->pick(1, 'Bravo#EUW');

        $standings = (new GetStandingsHandler($this->fixture->lans, $this->fixture->runs))->handle(LanChallengeFixture::LAN);
        $bravo = array_values(array_filter($standings->entries, static fn(StandingEntry $entry) => $entry->riotId === 'Bravo#EUW'))[0];

        $this->assertFalse($bravo->secretCard);
        $this->assertSame(2, $bravo->rerollsLeft);
    }

    public function testUneCarteSaboteurChoisieEstSecreteDansLeClassement(): void
    {
        $this->pick(2);

        $standings = (new GetStandingsHandler($this->fixture->lans, $this->fixture->runs))->handle(LanChallengeFixture::LAN);
        $alpha = array_values(array_filter($standings->entries, static fn(StandingEntry $entry) => $entry->riotId === 'Alpha#EUW'))[0];

        $this->assertNull($alpha->activeCard);
        $this->assertTrue($alpha->secretCard);
    }
}
