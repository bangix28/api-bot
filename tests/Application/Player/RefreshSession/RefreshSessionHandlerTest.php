<?php

namespace App\Tests\Application\Player\RefreshSession;

use App\Application\Player\IssuedSession;
use App\Application\Player\OpenSession\OpenSessionHandler;
use App\Application\Player\RefreshSession\RefreshSessionHandler;
use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\InvalidRefreshTokenException;
use App\Tests\Domain\Player\InMemoryPlayerSessionRepository;
use App\Tests\Domain\Player\ScriptedSessionTokenGenerator;
use App\Tests\Domain\Shared\FixedClock;
use PHPUnit\Framework\TestCase;

class RefreshSessionHandlerTest extends TestCase
{
    private InMemoryPlayerSessionRepository $sessions;
    private ScriptedSessionTokenGenerator $tokens;

    protected function setUp(): void
    {
        $this->sessions = new InMemoryPlayerSessionRepository();
        $this->tokens = new ScriptedSessionTokenGenerator();
    }

    private function openSession(string $now): OpenSessionHandler
    {
        return new OpenSessionHandler($this->sessions, $this->tokens, new FixedClock(new \DateTimeImmutable($now)));
    }

    private function refresh(string $plainToken, string $now): IssuedSession
    {
        $clock = new FixedClock(new \DateTimeImmutable($now));

        return (new RefreshSessionHandler($this->sessions, new OpenSessionHandler($this->sessions, $this->tokens, $clock), $clock))
            ->handle($plainToken);
    }

    public function testLaConnexionOuvreUneSessionDeTrenteJours(): void
    {
        $issued = $this->openSession('2026-11-01 12:00:00')->handle(new DiscordIdentity('1001', 'alpha'));

        $this->assertSame('rft_1', $issued->plainRefreshToken);
        $this->assertEquals(new \DateTimeImmutable('2026-12-01 12:00:00'), $issued->session->expiresAt);
        $this->assertSame($issued->session, $this->sessions->findByToken('rft_1'));
    }

    public function testRafraichirDonneUneSessionNeuvePourLeMemeJoueur(): void
    {
        $this->openSession('2026-11-01 12:00:00')->handle(new DiscordIdentity('1001', 'alpha'));

        $issued = $this->refresh('rft_1', '2026-11-20 08:00:00');

        $this->assertSame('rft_2', $issued->plainRefreshToken);
        $this->assertSame('1001', $issued->session->player->userId);
        $this->assertSame('alpha', $issued->session->player->username);
        $this->assertEquals(new \DateTimeImmutable('2026-12-20 08:00:00'), $issued->session->expiresAt, 'La durée repart à zéro.');
    }

    public function testLAncienJetonNeSertPlusApresLaGrace(): void
    {
        $this->openSession('2026-11-01 12:00:00')->handle(new DiscordIdentity('1001', 'alpha'));
        $this->refresh('rft_1', '2026-11-20 08:00:00');

        $this->assertSame('rft_3', $this->refresh('rft_1', '2026-11-20 08:00:30')->plainRefreshToken, 'Deux onglets en même temps : accepté.');

        $this->expectException(InvalidRefreshTokenException::class);
        $this->refresh('rft_1', '2026-11-20 08:00:31');
    }

    public function testUnJetonExpireEstRefuse(): void
    {
        $this->openSession('2026-11-01 12:00:00')->handle(new DiscordIdentity('1001', 'alpha'));

        $this->expectException(InvalidRefreshTokenException::class);
        $this->refresh('rft_1', '2026-12-01 12:00:01');
    }

    public function testUnJetonInconnuEstRefuse(): void
    {
        $this->expectException(InvalidRefreshTokenException::class);
        $this->refresh('rft_inconnu', '2026-11-01 12:00:00');
    }
}
