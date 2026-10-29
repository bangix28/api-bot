<?php

namespace App\Tests\Application\LanGame\AbandonStaleGames;

use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\Verdict;
use App\Domain\LanGame\LanGameStatus;
use App\Tests\Application\LanChallenge\LanChallengeFixture;
use App\Tests\Application\LanGame\LanGameFixture;
use App\Tests\Domain\LanGame\GameSnapshotBuilder;
use PHPUnit\Framework\TestCase;

class AbandonStaleGamesHandlerTest extends TestCase
{
    private LanGameFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new LanGameFixture();
        $this->fixture->picksDoubleKill(LanChallengeFixture::ALPHA);
        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->build(), 1);
    }

    public function testUnePartieSansReleveDepuisUneHeureEstRegleeEnVerdictInconnu(): void
    {
        $this->fixture->advance('+3601 seconds');

        $abandoned = $this->fixture->abandonStaleGames()->handle();

        $this->assertCount(1, $abandoned);
        $this->assertSame(LanGameStatus::ABANDONED, $abandoned[0]->status);
        $this->assertSame(LanGameStatus::ABANDONED, $this->fixture->games->all()[0]->status);
        $run = $this->fixture->run('puuid-a');
        $this->assertSame(Verdict::UNKNOWN, $run->results[0]->verdict);
        $this->assertSame(PlayerRun::ROUND_REROLLS_CAP, $run->rerollsLeft, 'La manche close recharge les relances.');
        $this->assertNull($run->lockedGameId, 'La carte n\'est plus bloquée.');
    }

    public function testUnePartieEncoreSuivieNEstPasAbandonnee(): void
    {
        $this->fixture->advance('+3600 seconds');

        $this->assertSame([], $this->fixture->abandonStaleGames()->handle());
        $this->assertSame(LanGameStatus::IN_PROGRESS, $this->fixture->games->all()[0]->status);
        $this->assertNotNull($this->fixture->run('puuid-a')->lockedGameId);
    }

    public function testUnePartieDejaRegleeNEstPasAbandonnee(): void
    {
        $this->fixture->advance('+1000 seconds');
        $this->fixture->receive(LanGameFixture::alphaAgent(), GameSnapshotBuilder::aGame()->at(1030)->build(), 2, final: true);
        $this->fixture->advance('+2 hours');

        $this->assertSame([], $this->fixture->abandonStaleGames()->handle());
        $this->assertSame(LanGameStatus::SETTLED, $this->fixture->games->all()[0]->status);
    }
}
