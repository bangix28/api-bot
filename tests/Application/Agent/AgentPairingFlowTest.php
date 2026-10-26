<?php

namespace App\Tests\Application\Agent;

use App\Application\Agent\ApproveDevice\ApproveDeviceCommand;
use App\Application\Agent\ExchangeDeviceCode\DeviceNotReadyException;
use App\Domain\Agent\DevicePollStatus;
use App\Domain\Agent\InvalidDeviceCodeException;
use App\Domain\Agent\InvalidUserCodeException;
use App\Domain\Agent\NotRegisteredInAnyLanException;
use App\Domain\Agent\SecretDigest;
use App\Domain\Player\DiscordIdentity;
use App\Tests\Domain\Agent\ScriptedSecretGenerator;
use PHPUnit\Framework\TestCase;

class AgentPairingFlowTest extends TestCase
{
    private AgentFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new AgentFixture();
    }

    private function exchangeStatus(string $deviceCode): DevicePollStatus
    {
        try {
            $this->fixture->exchangeDeviceCode()->handle($deviceCode);
        } catch (DeviceNotReadyException $exception) {
            return $exception->status;
        }

        return DevicePollStatus::APPROVED;
    }

    public function testLAgentRecoitUnCodeAAfficherEtUnSecretAGarder(): void
    {
        $view = $this->fixture->requestDeviceCode()->handle();

        $this->assertSame('K7QF-92PZ', $view->userCode);
        $this->assertSame('dev_1', $view->deviceCode);
        $this->assertSame(600, $view->expiresInSeconds);
        $this->assertSame(5, $view->pollIntervalSeconds);
        // La base ne garde que l'empreinte du secret.
        $this->assertArrayHasKey(SecretDigest::of('dev_1'), $this->fixture->authorizations->authorizations);
    }

    public function testUnCodeEncoreValideNEstJamaisRedistribue(): void
    {
        $this->fixture->secrets = new ScriptedSecretGenerator(['K7QF92PZ', 'K7QF92PZ', 'ABCD2345']);
        $this->fixture->requestDeviceCode()->handle();

        $second = $this->fixture->requestDeviceCode()->handle();

        $this->assertSame('ABCD-2345', $second->userCode);
    }

    public function testUnCodeExpirePeutResservir(): void
    {
        $this->fixture->requestDeviceCode()->handle();
        $this->fixture->advance('+11 minutes');

        $this->assertSame('K7QF-92PZ', $this->fixture->requestDeviceCode()->handle()->userCode);
    }

    public function testLeJoueurEstAppaireASaLanEnCours(): void
    {
        $this->fixture->requestDeviceCode()->handle();

        $pairing = $this->fixture->approveDevice()->handle(new ApproveDeviceCommand(AgentFixture::alpha(), 'k7qf-92pz'));

        $this->assertSame(AgentFixture::CURRENT_LAN, $pairing->lan->lanId);
        $this->assertSame(['Alpha#EUW'], $pairing->riotIds);
    }

    public function testSansLanEnCoursLeJoueurEstAppaireALaProchaine(): void
    {
        $this->fixture->requestDeviceCode()->handle();

        $pairing = $this->fixture->approveDevice()->handle(new ApproveDeviceCommand(AgentFixture::bravo(), 'K7QF92PZ'));

        $this->assertSame(AgentFixture::NEXT_LAN, $pairing->lan->lanId);
    }

    public function testUnJoueurInscritANulleLanEstPrevenuALaSaisie(): void
    {
        $this->fixture->requestDeviceCode()->handle();

        $this->expectException(NotRegisteredInAnyLanException::class);

        $this->fixture->approveDevice()->handle(new ApproveDeviceCommand(new DiscordIdentity('1003', 'charlie'), 'K7QF92PZ'));
    }

    public function testUnJoueurSansCompteEstPrevenuALaSaisie(): void
    {
        $this->fixture->requestDeviceCode()->handle();

        $this->expectException(NotRegisteredInAnyLanException::class);

        $this->fixture->approveDevice()->handle(new ApproveDeviceCommand(new DiscordIdentity('4242', 'inconnu'), 'K7QF92PZ'));
    }

    public function testUnCodeInconnuEstRefuse(): void
    {
        $this->expectException(InvalidUserCodeException::class);

        $this->fixture->approveDevice()->handle(new ApproveDeviceCommand(AgentFixture::alpha(), 'ABCD2345'));
    }

    public function testLeCycleCompletDeLAppairage(): void
    {
        $deviceCode = $this->fixture->requestDeviceCode()->handle()->deviceCode;

        $this->fixture->advance('+5 seconds');
        $this->assertSame(DevicePollStatus::PENDING, $this->exchangeStatus($deviceCode));

        $this->fixture->advance('+2 seconds');
        $this->assertSame(DevicePollStatus::SLOW_DOWN, $this->exchangeStatus($deviceCode));

        $this->fixture->approveDevice()->handle(new ApproveDeviceCommand(AgentFixture::alpha(), 'K7QF-92PZ'));
        $this->fixture->advance('+5 seconds');
        $issued = $this->fixture->exchangeDeviceCode()->handle($deviceCode);

        $this->assertSame('agt_1', $issued->plainToken);
        $this->assertSame(SecretDigest::of('agt_1'), $issued->token->digest);
        $this->assertSame('1001', $issued->token->player->userId);
        $this->assertSame(AgentFixture::CURRENT_LAN, $issued->token->lanId);
        $this->assertEquals(new \DateTimeImmutable('2026-11-08 04:00'), $issued->token->expiresAt, 'Le jeton expire à la fin de la LAN.');
        $this->assertSame(['Alpha#EUW'], $issued->pairing->riotIds);
        $this->assertNotNull($this->fixture->tokens->findActive('agt_1'));
    }

    public function testLeJetonNEstEmisQuUneFois(): void
    {
        $deviceCode = $this->fixture->requestDeviceCode()->handle()->deviceCode;
        $this->fixture->approveDevice()->handle(new ApproveDeviceCommand(AgentFixture::alpha(), 'K7QF92PZ'));
        $this->fixture->exchangeDeviceCode()->handle($deviceCode);

        $this->expectException(InvalidDeviceCodeException::class);

        $this->fixture->exchangeDeviceCode()->handle($deviceCode);
    }

    public function testUnNouvelAppairageRevoqueLAncienJeton(): void
    {
        foreach (['K7QF92PZ', 'ABCD2345'] as $userCode) {
            $this->fixture->secrets = new ScriptedSecretGenerator([$userCode]);
            $deviceCode = $this->fixture->requestDeviceCode()->handle()->deviceCode;
            $this->fixture->approveDevice()->handle(new ApproveDeviceCommand(AgentFixture::alpha(), $userCode));
            $this->fixture->exchangeDeviceCode()->handle($deviceCode);
            $this->fixture->advance('+1 minute');
        }

        $this->assertCount(1, $this->fixture->tokens->active);
        $this->assertCount(1, $this->fixture->tokens->revokedDigests);
    }

    public function testUnCodeExpireSansApprobationEstSignaleALAgent(): void
    {
        $deviceCode = $this->fixture->requestDeviceCode()->handle()->deviceCode;
        $this->fixture->advance('+11 minutes');

        $this->assertSame(DevicePollStatus::EXPIRED, $this->exchangeStatus($deviceCode));
    }

    public function testUnDeviceCodeInconnuEstRefuse(): void
    {
        $this->expectException(InvalidDeviceCodeException::class);

        $this->fixture->exchangeDeviceCode()->handle('dev_inconnu');
    }
}
