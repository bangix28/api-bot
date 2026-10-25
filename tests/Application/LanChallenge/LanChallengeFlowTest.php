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
use App\Domain\LanChallenge\PlayerRunNotFoundException;
use App\Domain\LanChallenge\RoundResolver;
use App\Tests\Domain\LanChallenge\InMemoryPlayerRunRepository;
use App\Tests\Domain\LanChallenge\PlayerGameStatsBuilder;
use App\Tests\Domain\LanChallenge\ScriptedRandomizer;
use PHPUnit\Framework\TestCase;

class LanChallengeFlowTest extends TestCase
{
    private const int LAN = 7;

    private InMemoryPlayerRunRepository $runs;
    private CardDealer $dealer;

    protected function setUp(): void
    {
        $this->runs = new InMemoryPlayerRunRepository();
        // Tirages par défaut (argent, première carte libre) : chaque offre vaut
        // [Doublé, Prudent, Esprit d'équipe].
        $this->dealer = new CardDealer(new ScriptedRandomizer());
    }

    private function join(string $puuid): void
    {
        (new JoinChallengesHandler($this->runs, $this->dealer))->handle(new JoinChallengesCommand(self::LAN, $puuid));
    }

    public function testRejoindreDistribueLaPremiereOffreUneSeuleFois(): void
    {
        $this->join('a');
        $first = $this->runs->find(self::LAN, 'a');

        // Rejoindre à nouveau ne doit pas redistribuer : ce serait une relance gratuite.
        (new JoinChallengesHandler($this->runs, new CardDealer(new ScriptedRandomizer([100, 0, 100, 0, 100, 0]))))
            ->handle(new JoinChallengesCommand(self::LAN, 'a'));

        $this->assertSame($first, $this->runs->find(self::LAN, 'a'));
    }

    public function testChoisirSansAvoirRejointEstRefuse(): void
    {
        $this->expectException(PlayerRunNotFoundException::class);

        (new PickCardHandler($this->runs))->handle(new PickCardCommand(self::LAN, 'inconnu', 0));
    }

    public function testRelancerEstPersiste(): void
    {
        $this->join('a');

        (new RerollCardHandler($this->runs, $this->dealer))->handle(new RerollCardCommand(self::LAN, 'a', 0));

        $this->assertSame(2, $this->runs->find(self::LAN, 'a')->rerollsLeft);
    }

    public function testUneMancheCompleteAPlusieurs(): void
    {
        $this->join('a');
        $this->join('b');
        $this->join('c');
        (new PickCardHandler($this->runs))->handle(new PickCardCommand(self::LAN, 'a', 0)); // Doublé
        (new PickCardHandler($this->runs))->handle(new PickCardCommand(self::LAN, 'b', 1)); // Prudent
        // c ne choisit pas : il joue sans carte et garde son offre.

        (new StartGameHandler($this->runs))->handle(new StartGameCommand(self::LAN, 'game-1', ['a', 'b', 'c', 'pas-inscrit']));

        $report = new GameReport('game-1', 18 * 60, [
            PlayerGameStatsBuilder::aPlayer('a')->withLargestMultiKill(2)->build(),
            PlayerGameStatsBuilder::aPlayer('b')->withDeaths(4)->build(),
            PlayerGameStatsBuilder::aPlayer('c')->withLargestMultiKill(5)->build(),
        ]);
        $settled = (new ResolveGameHandler($this->runs, new RoundResolver($this->dealer)))
            ->handle(new ResolveGameCommand(self::LAN, $report));

        $this->assertCount(2, $settled);
        // Les deux joueurs engagés réussissent : bonus de manche, 10 → 15.
        $this->assertSame(15, $this->runs->find(self::LAN, 'a')->score);
        $this->assertSame(15, $this->runs->find(self::LAN, 'b')->score);
        $this->assertNotNull($this->runs->find(self::LAN, 'a')->offer, 'Une nouvelle offre est distribuée.');

        $c = $this->runs->find(self::LAN, 'c');
        $this->assertSame(0, $c->score, 'Un penta sans carte engagée ne rapporte rien.');
        $this->assertSame([Challenge::DOUBLE_KILL, Challenge::PRUDENT, Challenge::TEAM_SPIRIT], $c->offer->cards);
    }

    public function testUnRelevePourUnePartieSansCarteEngageeNeChangeRien(): void
    {
        $this->join('a');

        $settled = (new ResolveGameHandler($this->runs, new RoundResolver($this->dealer)))
            ->handle(new ResolveGameCommand(self::LAN, new GameReport('game-x', 600, [])));

        $this->assertSame([], $settled);
        $this->assertSame(0, $this->runs->find(self::LAN, 'a')->score);
    }
}
