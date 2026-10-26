<?php

namespace App\Tests\Application\LanGame;

use App\Application\LanChallenge\JoinChallenges\JoinChallengesCommand;
use App\Application\LanChallenge\JoinChallenges\JoinChallengesHandler;
use App\Application\LanChallenge\PickCard\PickCardCommand;
use App\Application\LanChallenge\ResolveGame\ResolveGameHandler;
use App\Application\LanChallenge\StartGame\StartGameHandler;
use App\Application\LanGame\AbandonStaleGames\AbandonStaleGamesHandler;
use App\Application\LanGame\ReceiveReport\IgnoredReason;
use App\Application\LanGame\ReceiveReport\ReceiveReportCommand;
use App\Application\LanGame\ReceiveReport\ReceiveReportHandler;
use App\Domain\Agent\AgentToken;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\RoundResolver;
use App\Domain\LanGame\GameSnapshot;
use App\Domain\Player\DiscordIdentity;
use App\Tests\Application\LanChallenge\LanChallengeFixture;
use App\Tests\Domain\LanGame\InMemoryAgentReportLog;
use App\Tests\Domain\LanGame\InMemoryLanGameRepository;
use App\Tests\Domain\Logging\SpyLogger;
use App\Tests\Domain\Shared\FixedClock;

/**
 * La LAN de LanChallengeFixture (7, samedi 14:00 → dimanche 04:00), samedi à
 * 21:00, avec un agent chez Alpha et un chez Charlie. Les parties sont celles
 * de GameSnapshotBuilder : Alpha et Bravo chez ORDER, Charlie chez CHAOS.
 */
final class LanGameFixture
{
    public LanChallengeFixture $lan;
    public InMemoryAgentReportLog $reportLog;
    public InMemoryLanGameRepository $games;
    public SpyLogger $logger;

    public function __construct()
    {
        $this->lan = new LanChallengeFixture();
        $this->reportLog = new InMemoryAgentReportLog();
        $this->games = new InMemoryLanGameRepository($this->reportLog);
        $this->logger = new SpyLogger();
    }

    public static function alphaAgent(): AgentToken
    {
        return new AgentToken('digest-alpha', new DiscordIdentity(LanChallengeFixture::ALPHA, 'alpha'), LanChallengeFixture::LAN, new \DateTimeImmutable('2026-11-08 04:00'));
    }

    public static function charlieAgent(): AgentToken
    {
        return new AgentToken('digest-charlie', new DiscordIdentity(LanChallengeFixture::CHARLIE, 'charlie'), LanChallengeFixture::LAN, new \DateTimeImmutable('2026-11-08 04:00'));
    }

    public function at(string $dateTime): self
    {
        $this->lan->clock = new FixedClock(new \DateTimeImmutable($dateTime));

        return $this;
    }

    public function advance(string $offset): self
    {
        $this->lan->clock = new FixedClock($this->lan->clock->now()->modify($offset));

        return $this;
    }

    /** Le joueur rejoint les défis et choisit la première carte de son offre (Doublé). */
    public function picksDoubleKill(string $discordId): void
    {
        (new JoinChallengesHandler($this->lan->seats(), $this->lan->runs, $this->lan->dealer))
            ->handle(new JoinChallengesCommand(LanChallengeFixture::LAN, $discordId));
        $this->lan->pickCardHandler()->handle(new PickCardCommand(LanChallengeFixture::LAN, $discordId, 0));
    }

    public function receive(AgentToken $token, GameSnapshot $snapshot, int $sequence, bool $final = false, string $sessionId = 'session-1'): ?IgnoredReason
    {
        return $this->receiveReport()->handle(new ReceiveReportCommand($token, $sessionId, $sequence, $final, $snapshot, '{"brut":' . $sequence . '}'));
    }

    public function receiveReport(): ReceiveReportHandler
    {
        return new ReceiveReportHandler(
            $this->lan->lans, $this->lan->accounts, $this->games, $this->reportLog,
            new StartGameHandler($this->lan->runs), $this->resolveGame(), $this->lan->clock, $this->logger,
        );
    }

    public function abandonStaleGames(): AbandonStaleGamesHandler
    {
        return new AbandonStaleGamesHandler($this->games, $this->resolveGame(), $this->lan->clock);
    }

    public function run(string $puuid): PlayerRun
    {
        return $this->lan->runs->find(LanChallengeFixture::LAN, $puuid) ?? throw new \LogicException('Parcours introuvable : ' . $puuid);
    }

    private function resolveGame(): ResolveGameHandler
    {
        return new ResolveGameHandler($this->lan->runs, new RoundResolver($this->lan->dealer));
    }
}
