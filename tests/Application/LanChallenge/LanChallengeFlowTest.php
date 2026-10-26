<?php

namespace App\Tests\Application\LanChallenge;

use App\Application\LanChallenge\JoinChallenges\JoinChallengesCommand;
use App\Application\LanChallenge\JoinChallenges\JoinChallengesHandler;
use App\Application\LanChallenge\PickCard\PickCardCommand;
use App\Application\LanChallenge\PickCard\PickCardHandler;
use App\Application\LanChallenge\RerollCard\RerollCardCommand;
use App\Application\LanChallenge\RerollCard\RerollCardHandler;
use App\Application\LanChallenge\ResolveGame\ResolveGameCommand;
use App\Application\LanChallenge\ResolveGame\ResolveGameHandler;
use App\Application\LanChallenge\StartGame\StartGameCommand;
use App\Application\LanChallenge\StartGame\StartGameHandler;
use App\Domain\LanChallenge\CardDealer;
use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\GameReport;
use App\Domain\LanChallenge\LanFinishedException;
use App\Domain\LanChallenge\PlayerRunNotFoundException;
use App\Domain\LanChallenge\RoundResolver;
use App\Tests\Domain\LanChallenge\PlayerGameStatsBuilder;
use App\Tests\Domain\LanChallenge\ScriptedRandomizer;
use App\Tests\Domain\Shared\FixedClock;
use PHPUnit\Framework\TestCase;

class LanChallengeFlowTest extends TestCase
{
    private LanChallengeFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new LanChallengeFixture();
    }

    private function join(string $discordId, ?CardDealer $dealer = null): void
    {
        (new JoinChallengesHandler($this->fixture->seats(), $this->fixture->runs, $dealer ?? $this->fixture->dealer))
            ->handle(new JoinChallengesCommand(LanChallengeFixture::LAN, $discordId));
    }

    private function pick(string $discordId, int $slot): void
    {
        (new PickCardHandler($this->fixture->seats(), $this->fixture->runs))
            ->handle(new PickCardCommand(LanChallengeFixture::LAN, $discordId, $slot));
    }

    public function testRejoindreDistribueLaPremiereOffreUneSeuleFois(): void
    {
        $this->join(LanChallengeFixture::ALPHA);
        $first = $this->fixture->runs->find(LanChallengeFixture::LAN, 'puuid-a');

        // Rejoindre à nouveau ne doit pas redistribuer : ce serait une relance gratuite.
        $this->join(LanChallengeFixture::ALPHA, new CardDealer(new ScriptedRandomizer([100, 0, 100, 0, 100, 0])));

        $this->assertSame($first, $this->fixture->runs->find(LanChallengeFixture::LAN, 'puuid-a'));
    }

    public function testRejoindreUneLanTermineeEstRefuse(): void
    {
        $this->fixture = new LanChallengeFixture(new \DateTimeImmutable('2026-11-08 04:01'));

        $this->expectException(LanFinishedException::class);

        $this->join(LanChallengeFixture::ALPHA);
    }

    public function testChoisirApresLaFinDeLaLanEstRefuse(): void
    {
        $this->join(LanChallengeFixture::ALPHA);
        $this->fixture->clock = new FixedClock(new \DateTimeImmutable('2026-11-08 04:01'));

        $this->expectException(LanFinishedException::class);

        $this->pick(LanChallengeFixture::ALPHA, 0);
    }

    public function testRelancerApresLaFinDeLaLanEstRefuse(): void
    {
        $this->join(LanChallengeFixture::ALPHA);
        $this->fixture->clock = new FixedClock(new \DateTimeImmutable('2026-11-08 04:01'));

        $this->expectException(LanFinishedException::class);

        (new RerollCardHandler($this->fixture->seats(), $this->fixture->runs, $this->fixture->dealer))
            ->handle(new RerollCardCommand(LanChallengeFixture::LAN, LanChallengeFixture::ALPHA, 0));
    }

    public function testChoisirSansAvoirRejointEstRefuse(): void
    {
        $this->expectException(PlayerRunNotFoundException::class);

        $this->pick(LanChallengeFixture::ALPHA, 0);
    }

    public function testRelancerEstPersiste(): void
    {
        $this->join(LanChallengeFixture::ALPHA);

        (new RerollCardHandler($this->fixture->seats(), $this->fixture->runs, $this->fixture->dealer))
            ->handle(new RerollCardCommand(LanChallengeFixture::LAN, LanChallengeFixture::ALPHA, 0));

        $this->assertSame(2, $this->fixture->runs->find(LanChallengeFixture::LAN, 'puuid-a')->rerollsLeft);
    }

    public function testUneMancheCompleteAPlusieurs(): void
    {
        $this->join(LanChallengeFixture::ALPHA);
        $this->join(LanChallengeFixture::BRAVO);
        $this->join(LanChallengeFixture::CHARLIE);
        $this->pick(LanChallengeFixture::ALPHA, 0); // Doublé
        $this->pick(LanChallengeFixture::BRAVO, 1); // Prudent
        // Charlie ne choisit pas : il joue sans carte et garde son offre.

        (new StartGameHandler($this->fixture->runs))
            ->handle(new StartGameCommand(LanChallengeFixture::LAN, 'game-1', ['puuid-a', 'puuid-b', 'puuid-c', 'pas-inscrit']));

        $report = new GameReport('game-1', 18 * 60, [
            PlayerGameStatsBuilder::aPlayer('puuid-a')->withLargestMultiKill(2)->build(),
            PlayerGameStatsBuilder::aPlayer('puuid-b')->withDeaths(4)->build(),
            PlayerGameStatsBuilder::aPlayer('puuid-c')->withLargestMultiKill(5)->build(),
        ]);
        $settled = (new ResolveGameHandler($this->fixture->runs, new RoundResolver($this->fixture->dealer)))
            ->handle(new ResolveGameCommand(LanChallengeFixture::LAN, $report));

        $this->assertCount(2, $settled);
        // Les deux joueurs engagés réussissent : bonus de manche, 10 → 15.
        $this->assertSame(15, $this->fixture->runs->find(LanChallengeFixture::LAN, 'puuid-a')->score);
        $this->assertSame(15, $this->fixture->runs->find(LanChallengeFixture::LAN, 'puuid-b')->score);
        $this->assertNotNull($this->fixture->runs->find(LanChallengeFixture::LAN, 'puuid-a')->offer, 'Une nouvelle offre est distribuée.');

        $playerWithoutCard = $this->fixture->runs->find(LanChallengeFixture::LAN, 'puuid-c');
        $this->assertSame(0, $playerWithoutCard->score, 'Un penta sans carte engagée ne rapporte rien.');
        $this->assertSame([Challenge::DOUBLE_KILL, Challenge::PRUDENT, Challenge::TEAM_SPIRIT], $playerWithoutCard->offer->cards);
    }

    public function testUnRelevePourUnePartieSansCarteEngageeNeChangeRien(): void
    {
        $this->join(LanChallengeFixture::ALPHA);

        $settled = (new ResolveGameHandler($this->fixture->runs, new RoundResolver($this->fixture->dealer)))
            ->handle(new ResolveGameCommand(LanChallengeFixture::LAN, new GameReport('game-x', 600, [])));

        $this->assertSame([], $settled);
        $this->assertSame(0, $this->fixture->runs->find(LanChallengeFixture::LAN, 'puuid-a')->score);
    }
}
