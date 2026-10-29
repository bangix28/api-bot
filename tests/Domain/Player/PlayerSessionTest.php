<?php

namespace App\Tests\Domain\Player;

use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\PlayerSession;
use PHPUnit\Framework\TestCase;

class PlayerSessionTest extends TestCase
{
    private static function openedAt(string $instant): PlayerSession
    {
        return PlayerSession::open('empreinte', new DiscordIdentity('1001', 'alpha'), new \DateTimeImmutable($instant));
    }

    public function testUneSessionDureTrenteJours(): void
    {
        $session = self::openedAt('2026-11-01 12:00:00');

        $this->assertFalse($session->isExpiredAt(new \DateTimeImmutable('2026-12-01 12:00:00')));
        $this->assertTrue($session->isExpiredAt(new \DateTimeImmutable('2026-12-01 12:00:01')));
    }

    public function testUneSessionRetireeResteAccepteeQuelquesSecondes(): void
    {
        $retired = self::openedAt('2026-11-01 12:00:00')->retire(new \DateTimeImmutable('2026-11-05 10:00:00'));

        $this->assertFalse($retired->isExpiredAt(new \DateTimeImmutable('2026-11-05 10:00:30')));
        $this->assertTrue($retired->isExpiredAt(new \DateTimeImmutable('2026-11-05 10:00:31')));
    }

    public function testRetirerNeProlongeJamaisUneSessionQuiExpireAvant(): void
    {
        $retired = self::openedAt('2026-11-01 12:00:00')->retire(new \DateTimeImmutable('2026-12-01 11:59:50'));

        $this->assertEquals(new \DateTimeImmutable('2026-12-01 12:00:00'), $retired->expiresAt);
    }
}
