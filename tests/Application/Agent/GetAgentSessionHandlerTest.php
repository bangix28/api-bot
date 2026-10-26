<?php

namespace App\Tests\Application\Agent;

use App\Domain\Agent\AgentSettings;
use App\Domain\Agent\AgentToken;
use App\Domain\Agent\AgentTokenExpiredException;
use App\Domain\LanChallenge\LanNotFoundException;
use PHPUnit\Framework\TestCase;

class GetAgentSessionHandlerTest extends TestCase
{
    private static function tokenFor(int $lanId, string $expiresAt): AgentToken
    {
        return new AgentToken('empreinte', AgentFixture::alpha(), $lanId, new \DateTimeImmutable($expiresAt));
    }

    public function testPendantLaLanLAgentEnvoie(): void
    {
        $session = (new AgentFixture())->agentSession()->handle(self::tokenFor(AgentFixture::CURRENT_LAN, '2026-11-08 04:00'));

        $this->assertTrue($session->lanInProgress);
        $this->assertSame(['Alpha#EUW'], $session->riotIds);
        $this->assertSame(['KIWI'], $session->acceptedGameModes);
        $this->assertSame(AgentSettings::REPORT_INTERVAL_SECONDS, $session->reportIntervalSeconds);
    }

    public function testAvantLaLanLAgentAttend(): void
    {
        $session = (new AgentFixture())->agentSession()->handle(self::tokenFor(AgentFixture::NEXT_LAN, '2026-11-15 04:00'));

        $this->assertFalse($session->lanInProgress);
        $this->assertSame(AgentFixture::NEXT_LAN, $session->lan->lanId);
    }

    public function testApresLaLanLaSessionEstTerminee(): void
    {
        // La grâce d'une heure ne vaut que pour le relevé final, pas pour la session.
        $this->expectException(AgentTokenExpiredException::class);

        (new AgentFixture())->agentSession()->handle(self::tokenFor(AgentFixture::CURRENT_LAN, '2026-11-07 19:59'));
    }

    public function testUneLanSupprimeeEstSignalee(): void
    {
        $this->expectException(LanNotFoundException::class);

        (new AgentFixture())->agentSession()->handle(self::tokenFor(999, '2026-11-08 04:00'));
    }
}
