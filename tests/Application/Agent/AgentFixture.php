<?php

namespace App\Tests\Application\Agent;

use App\Application\Agent\ApproveDevice\ApproveDeviceHandler;
use App\Application\Agent\ExchangeDeviceCode\ExchangeDeviceCodeHandler;
use App\Application\Agent\GetAgentSession\GetAgentSessionHandler;
use App\Application\Agent\RequestDeviceCode\RequestDeviceCodeHandler;
use App\Domain\LanChallenge\LanSummary;
use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\TrackedRiotAccount;
use App\Tests\Domain\Agent\InMemoryAgentTokenRepository;
use App\Tests\Domain\Agent\InMemoryDeviceAuthorizationRepository;
use App\Tests\Domain\Agent\ScriptedSecretGenerator;
use App\Tests\Domain\LanChallenge\InMemoryLanSummaryReader;
use App\Tests\Domain\Player\InMemoryTrackedRiotAccountRepository;
use App\Tests\Domain\Shared\FixedClock;

/**
 * Le samedi 7 novembre à 20:00 :
 * - LAN 3, en cours (14:00 → 04:00), à laquelle participe Alpha#EUW ;
 * - LAN 4, la semaine suivante, à laquelle participe Bravo#EUW ;
 * - Charlie possède un compte qui ne participe à aucune LAN.
 */
final class AgentFixture
{
    public const int CURRENT_LAN = 3;
    public const int NEXT_LAN = 4;

    public InMemoryLanSummaryReader $lans;
    public InMemoryTrackedRiotAccountRepository $accounts;
    public InMemoryDeviceAuthorizationRepository $authorizations;
    public InMemoryAgentTokenRepository $tokens;
    public ScriptedSecretGenerator $secrets;
    public FixedClock $clock;

    public function __construct()
    {
        $this->lans = (new InMemoryLanSummaryReader())
            ->add(new LanSummary(self::CURRENT_LAN, 'LAN d\'automne', new \DateTimeImmutable('2026-11-07 14:00'), new \DateTimeImmutable('2026-11-08 04:00'), ['puuid-a' => 'Alpha#EUW']))
            ->add(new LanSummary(self::NEXT_LAN, 'LAN d\'hiver', new \DateTimeImmutable('2026-11-14 14:00'), new \DateTimeImmutable('2026-11-15 04:00'), ['puuid-b' => 'Bravo#EUW']));
        $this->accounts = (new InMemoryTrackedRiotAccountRepository())
            ->add(new TrackedRiotAccount('Alpha#EUW', 'puuid-a', '1001'))
            ->add(new TrackedRiotAccount('Bravo#EUW', 'puuid-b', '1002'))
            ->add(new TrackedRiotAccount('Charlie#EUW', 'puuid-c', '1003'));
        $this->authorizations = new InMemoryDeviceAuthorizationRepository();
        $this->tokens = new InMemoryAgentTokenRepository();
        $this->secrets = new ScriptedSecretGenerator();
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-11-07 20:00:00'));
    }

    public static function alpha(): DiscordIdentity
    {
        return new DiscordIdentity('1001', 'alpha');
    }

    public static function bravo(): DiscordIdentity
    {
        return new DiscordIdentity('1002', 'bravo');
    }

    public function requestDeviceCode(): RequestDeviceCodeHandler
    {
        return new RequestDeviceCodeHandler($this->authorizations, $this->secrets, $this->clock);
    }

    public function approveDevice(): ApproveDeviceHandler
    {
        return new ApproveDeviceHandler($this->authorizations, $this->accounts, $this->lans, $this->clock);
    }

    public function exchangeDeviceCode(): ExchangeDeviceCodeHandler
    {
        return new ExchangeDeviceCodeHandler($this->authorizations, $this->tokens, $this->accounts, $this->lans, $this->secrets, $this->clock);
    }

    public function agentSession(): GetAgentSessionHandler
    {
        return new GetAgentSessionHandler($this->accounts, $this->lans, $this->clock);
    }

    public function advance(string $offset): void
    {
        $this->clock = new FixedClock($this->clock->now()->modify($offset));
    }
}
