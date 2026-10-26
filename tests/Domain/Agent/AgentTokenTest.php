<?php

namespace App\Tests\Domain\Agent;

use App\Domain\Agent\AgentToken;
use App\Domain\Player\DiscordIdentity;
use PHPUnit\Framework\TestCase;

class AgentTokenTest extends TestCase
{
    private static function token(): AgentToken
    {
        return new AgentToken('empreinte', new DiscordIdentity('1001', 'alpha'), 3, new \DateTimeImmutable('2026-11-08 04:00:00'));
    }

    public function testLeJetonExpireALaFinDeLaLan(): void
    {
        $this->assertFalse(self::token()->isExpiredAt(new \DateTimeImmutable('2026-11-08 04:00:00')));
        $this->assertTrue(self::token()->isExpiredAt(new \DateTimeImmutable('2026-11-08 04:00:01')));
    }

    public function testLeReleveFinalBeneficieDUneHeureDeGrace(): void
    {
        $this->assertTrue(self::token()->acceptsFinalReportAt(new \DateTimeImmutable('2026-11-08 05:00:00')));
        $this->assertFalse(self::token()->acceptsFinalReportAt(new \DateTimeImmutable('2026-11-08 05:00:01')));
    }
}
