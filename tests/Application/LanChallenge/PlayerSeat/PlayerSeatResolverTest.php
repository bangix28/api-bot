<?php

namespace App\Tests\Application\LanChallenge\PlayerSeat;

use App\Domain\LanChallenge\LanFinishedException;
use App\Domain\LanChallenge\LanNotFoundException;
use App\Domain\LanChallenge\NotALanParticipantException;
use App\Domain\LanChallenge\SeveralAccountsInLanException;
use App\Domain\Player\TrackedRiotAccount;
use App\Tests\Application\LanChallenge\LanChallengeFixture;
use PHPUnit\Framework\TestCase;

class PlayerSeatResolverTest extends TestCase
{
    public function testLeJoueurJoueAvecSonCompteParticipant(): void
    {
        $seat = (new LanChallengeFixture())->seats()->resolve(LanChallengeFixture::LAN, LanChallengeFixture::BRAVO);

        $this->assertSame('puuid-b', $seat->puuid);
        $this->assertSame('Bravo#EUW', $seat->riotId);
        $this->assertSame(LanChallengeFixture::LAN, $seat->lan->lanId);
    }

    public function testSonSmurfNonInscritALaLanEstIgnore(): void
    {
        // Le smurf de Bravo n'est pas inscrit à la LAN : seul son compte principal joue.
        $fixture = new LanChallengeFixture();
        $fixture->accounts->add(new TrackedRiotAccount('Smurf#EUW', 'puuid-smurf', LanChallengeFixture::BRAVO));

        $this->assertSame('puuid-b', $fixture->seats()->resolve(LanChallengeFixture::LAN, LanChallengeFixture::BRAVO)->puuid);
    }

    public function testUneLanInconnueEstSignalee(): void
    {
        $this->expectException(LanNotFoundException::class);

        (new LanChallengeFixture())->seats()->resolve(999, LanChallengeFixture::ALPHA);
    }

    public function testUnJoueurSansCompteInscritNeParticipePas(): void
    {
        $this->expectException(NotALanParticipantException::class);

        (new LanChallengeFixture())->seats()->resolve(LanChallengeFixture::LAN, '4242');
    }

    public function testDeuxComptesInscritsALaMemeLanSontAmbigus(): void
    {
        // Alpha possède aussi le compte de Bravo : impossible de savoir lequel il joue.
        $fixture = new LanChallengeFixture();
        $fixture->accounts->add(new TrackedRiotAccount('Bravo#EUW', 'puuid-b', LanChallengeFixture::ALPHA));

        $this->expectException(SeveralAccountsInLanException::class);

        $fixture->seats()->resolve(LanChallengeFixture::LAN, LanChallengeFixture::ALPHA);
    }

    public function testOnAgitJusquALHeureDeFinIncluse(): void
    {
        $fixture = new LanChallengeFixture(new \DateTimeImmutable('2026-11-08 04:00'));

        $this->assertSame('puuid-a', $fixture->seats()->resolveForAction(LanChallengeFixture::LAN, LanChallengeFixture::ALPHA)->puuid);
    }

    public function testApresLaFinOnNAgitPlus(): void
    {
        $fixture = new LanChallengeFixture(new \DateTimeImmutable('2026-11-08 04:01'));

        $this->expectException(LanFinishedException::class);

        $fixture->seats()->resolveForAction(LanChallengeFixture::LAN, LanChallengeFixture::ALPHA);
    }

    public function testApresLaFinOnPeutEncoreConsulter(): void
    {
        $fixture = new LanChallengeFixture(new \DateTimeImmutable('2026-11-09 12:00'));

        $this->assertSame('puuid-a', $fixture->seats()->resolve(LanChallengeFixture::LAN, LanChallengeFixture::ALPHA)->puuid);
    }
}
