<?php

namespace App\Tests\Domain\Player;

use App\Domain\Player\DiscordIdentity;
use App\Domain\Player\RiotId;
use App\Domain\Player\TrackedRiotAccount;
use App\Domain\Player\TrackedRiotAccountRepositoryInterface;

final class InMemoryTrackedRiotAccountRepository implements TrackedRiotAccountRepositoryInterface
{
    /** @var array<string, TrackedRiotAccount> indexés par puuid */
    private array $accounts = [];

    /** @var array<string, string> nom Discord retenu à la liaison, par puuid */
    public array $linkedUsernames = [];

    public function add(TrackedRiotAccount $account): self
    {
        $this->accounts[$account->puuid] = $account;

        return $this;
    }

    public function findByRiotId(RiotId $riotId): ?TrackedRiotAccount
    {
        foreach ($this->accounts as $account) {
            if (strcasecmp($account->riotId, $riotId->value) === 0) {
                return $account;
            }
        }

        return null;
    }

    /** @return TrackedRiotAccount[] */
    public function findOwnedBy(string $discordId): array
    {
        return array_values(array_filter(
            $this->accounts,
            static fn(TrackedRiotAccount $account) => $account->isOwnedBy($discordId),
        ));
    }

    public function linkTo(string $puuid, DiscordIdentity $player): void
    {
        $account = $this->accounts[$puuid] ?? throw new \LogicException("Aucun compte suivi pour le puuid $puuid.");

        $this->accounts[$puuid] = new TrackedRiotAccount($account->riotId, $account->puuid, $player->userId);
        $this->linkedUsernames[$puuid] = $player->username;
    }
}
