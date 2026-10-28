<?php

namespace App\Application\Agent\GetAgentSession;

use App\Domain\Agent\AgentSettings;
use App\Domain\Agent\AgentToken;
use App\Domain\Agent\AgentTokenExpiredException;
use App\Domain\LanChallenge\LanNotFoundException;
use App\Domain\LanChallenge\LanSummaryReaderInterface;
use App\Domain\Player\TrackedRiotAccount;
use App\Domain\Player\TrackedRiotAccountRepositoryInterface;
use App\Domain\Shared\ClockInterface;

/**
 * Session de l'agent (contrat, § 2) : le jeton vaut-il encore, la LAN a-t-elle
 * commencé, quels réglages appliquer ?
 */
final readonly class GetAgentSessionHandler
{
    public function __construct(
        private TrackedRiotAccountRepositoryInterface $accounts,
        private LanSummaryReaderInterface $lans,
        private ClockInterface $clock,
    ) {
    }

    public function handle(AgentToken $token): AgentSessionView
    {
        $now = $this->clock->now();

        // La grâce d'une heure ne vaut que pour le relevé final : la session,
        // elle, s'arrête avec la LAN.
        if ($token->isExpiredAt($now)) {
            throw new AgentTokenExpiredException('La LAN de ce jeton est terminée.');
        }

        $lan = $this->lans->find($token->lanId)
            ?? throw new LanNotFoundException('La LAN de ce jeton n\'existe plus.');

        return new AgentSessionView(
            array_map(static fn(TrackedRiotAccount $account) => $account->riotId, $this->accounts->findOwnedBy($token->player->userId)),
            $lan,
            $now >= $lan->startsAt,
            AgentSettings::REPORT_INTERVAL_SECONDS,
        );
    }
}
