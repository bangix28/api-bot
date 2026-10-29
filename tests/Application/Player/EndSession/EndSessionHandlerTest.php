<?php

namespace App\Tests\Application\Player\EndSession;

use App\Application\Player\EndSession\EndSessionHandler;
use App\Application\Player\OpenSession\OpenSessionHandler;
use App\Domain\Player\DiscordIdentity;
use App\Tests\Domain\Player\InMemoryPlayerSessionRepository;
use App\Tests\Domain\Player\ScriptedSessionTokenGenerator;
use App\Tests\Domain\Shared\FixedClock;
use PHPUnit\Framework\TestCase;

class EndSessionHandlerTest extends TestCase
{
    private InMemoryPlayerSessionRepository $sessions;
    private OpenSessionHandler $openSession;

    protected function setUp(): void
    {
        $this->sessions = new InMemoryPlayerSessionRepository();
        $this->openSession = new OpenSessionHandler(
            $this->sessions,
            new ScriptedSessionTokenGenerator(),
            new FixedClock(new \DateTimeImmutable('2026-11-01 12:00:00')),
        );
    }

    public function testSeDeconnecterSupprimeLaSessionDeCetAppareilSeulement(): void
    {
        $this->openSession->handle(new DiscordIdentity('1001', 'alpha'));
        $this->openSession->handle(new DiscordIdentity('1001', 'alpha'));

        (new EndSessionHandler($this->sessions))->handle('rft_1');

        $this->assertNull($this->sessions->findByToken('rft_1'));
        $this->assertNotNull($this->sessions->findByToken('rft_2'), 'L\'autre appareil reste connecté.');
    }

    public function testSeDeconnecterAvecUnJetonInconnuNeFaitRien(): void
    {
        $this->openSession->handle(new DiscordIdentity('1001', 'alpha'));

        (new EndSessionHandler($this->sessions))->handle('rft_inconnu');

        $this->assertCount(1, $this->sessions->sessions);
    }
}
