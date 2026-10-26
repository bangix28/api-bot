<?php

namespace App\Application\Agent\ApproveDevice;

use App\Application\Agent\AgentPairingView;
use App\Domain\Agent\DeviceAuthorizationRepositoryInterface;
use App\Domain\Agent\InvalidUserCodeException;
use App\Domain\Agent\NotRegisteredInAnyLanException;
use App\Domain\Agent\UserCode;
use App\Domain\LanChallenge\LanSummaryReaderInterface;
use App\Domain\Player\TrackedRiotAccount;
use App\Domain\Player\TrackedRiotAccountRepositoryInterface;
use App\Domain\Shared\ClockInterface;

/**
 * Le joueur connecté sur le front saisit le code de son agent (contrat, § 1.2).
 * L'agent est appairé à ce joueur et à sa LAN en cours, sinon la prochaine.
 */
final readonly class ApproveDeviceHandler
{
    public function __construct(
        private DeviceAuthorizationRepositoryInterface $authorizations,
        private TrackedRiotAccountRepositoryInterface $accounts,
        private LanSummaryReaderInterface $lans,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ApproveDeviceCommand $command): AgentPairingView
    {
        $now = $this->clock->now();

        $authorization = $this->authorizations->findByUserCode(new UserCode($command->userCode))
            ?? throw new InvalidUserCodeException('Code inconnu ou expiré : relance la connexion depuis l\'agent.');

        $ownedAccounts = $this->accounts->findOwnedBy($command->player->userId);
        $lan = $this->lans->findCurrentOrNextFor(
            array_map(static fn(TrackedRiotAccount $account) => $account->puuid, $ownedAccounts),
            $now,
        ) ?? throw new NotRegisteredInAnyLanException('Tu n\'es inscrit à aucune LAN en cours ou à venir.');

        $this->authorizations->save($authorization->approve($command->player, $lan->lanId, $now));

        return new AgentPairingView($lan, array_map(static fn(TrackedRiotAccount $account) => $account->riotId, $ownedAccounts));
    }
}
