<?php

namespace App\Application\LanChallenge\GetCurrentLan;

use App\Domain\LanChallenge\LanSummaryReaderInterface;
use App\Domain\Player\TrackedRiotAccount;
use App\Domain\Player\TrackedRiotAccountRepositoryInterface;
use App\Domain\Shared\ClockInterface;

/**
 * La LAN qui concerne le joueur connecté : celle en cours à laquelle l'un de
 * ses comptes participe, sinon la prochaine. C'est elle que le front ouvre
 * (`lanId` des routes des défis), et la même que celle de l'appairage de l'agent.
 */
final readonly class GetCurrentLanHandler
{
    public function __construct(
        private TrackedRiotAccountRepositoryInterface $accounts,
        private LanSummaryReaderInterface $lans,
        private ClockInterface $clock,
    ) {
    }

    /** @return CurrentLanView|null null : inscrit à aucune LAN en cours ou à venir */
    public function handle(string $discordId): ?CurrentLanView
    {
        $now = $this->clock->now();
        $ownedAccounts = $this->accounts->findOwnedBy($discordId);

        $lan = $this->lans->findCurrentOrNextFor(
            array_map(static fn(TrackedRiotAccount $account) => $account->puuid, $ownedAccounts),
            $now,
        );
        if ($lan === null) {
            return null;
        }

        $registeredAccounts = array_filter($ownedAccounts, static fn(TrackedRiotAccount $account) => $lan->hasParticipant($account->puuid));

        return new CurrentLanView(
            $lan,
            $now >= $lan->startsAt,
            array_values(array_map(static fn(TrackedRiotAccount $account) => $account->riotId, $registeredAccounts)),
        );
    }
}
