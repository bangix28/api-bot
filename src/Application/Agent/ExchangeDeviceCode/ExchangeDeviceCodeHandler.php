<?php

namespace App\Application\Agent\ExchangeDeviceCode;

use App\Application\Agent\AgentPairingView;
use App\Domain\Agent\AgentToken;
use App\Domain\Agent\AgentTokenRepositoryInterface;
use App\Domain\Agent\DeviceAuthorizationRepositoryInterface;
use App\Domain\Agent\DevicePollStatus;
use App\Domain\Agent\InvalidDeviceCodeException;
use App\Domain\Agent\SecretDigest;
use App\Domain\Agent\SecretGeneratorInterface;
use App\Domain\LanChallenge\LanNotFoundException;
use App\Domain\LanChallenge\LanSummaryReaderInterface;
use App\Domain\Player\TrackedRiotAccount;
use App\Domain\Player\TrackedRiotAccountRepositoryInterface;
use App\Domain\Shared\ClockInterface;

/**
 * L'agent interroge avec son deviceCode (contrat, § 1.3). Tant que le joueur n'a
 * pas approuvé, il est invité à réessayer ; une fois approuvé, le jeton est
 * émis une seule fois et remplace le précédent du joueur pour cette LAN.
 */
final readonly class ExchangeDeviceCodeHandler
{
    public function __construct(
        private DeviceAuthorizationRepositoryInterface $authorizations,
        private AgentTokenRepositoryInterface $tokens,
        private TrackedRiotAccountRepositoryInterface $accounts,
        private LanSummaryReaderInterface $lans,
        private SecretGeneratorInterface $secrets,
        private ClockInterface $clock,
    ) {
    }

    public function handle(string $deviceCode): IssuedAgentToken
    {
        $authorization = $this->authorizations->findByDeviceCode($deviceCode)
            ?? throw new InvalidDeviceCodeException('Code d\'appareil inconnu.');

        $outcome = $authorization->poll($this->clock->now());
        if ($outcome->status !== DevicePollStatus::APPROVED) {
            $this->authorizations->save($outcome->authorization);

            throw new DeviceNotReadyException($outcome->status);
        }

        $approval = $outcome->authorization->approval;
        $lan = $this->lans->find($approval->lanId)
            ?? throw new LanNotFoundException('La LAN de cet appairage n\'existe plus.');

        $plainToken = $this->secrets->agentToken();
        $token = new AgentToken(SecretDigest::of($plainToken), $approval->player, $lan->lanId, $lan->endsAt);
        $this->tokens->saveReplacingPrevious($token);
        $this->authorizations->save($outcome->authorization->consume());

        return new IssuedAgentToken($plainToken, $token, new AgentPairingView(
            $lan,
            array_map(static fn(TrackedRiotAccount $account) => $account->riotId, $this->accounts->findOwnedBy($approval->player->userId)),
        ));
    }
}
