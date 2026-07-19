<?php

namespace App\Tests\Domain\RiotAccount;

use App\Domain\RiotAccount\RiotAccountEntity;
use App\Domain\RiotAccount\RiotAccountRepositoryInterface;

class InMemoryRiotAccountRepository implements RiotAccountRepositoryInterface
{
    private array $accounts = [];

    /** @param RiotAccountEntity[] $accounts */
    public function __construct(array $accounts = [])
    {
        foreach ($accounts as $account) {
            $this->accounts[$account->getRiotID()] = $account;
        }
    }

    /** @return RiotAccountEntity[] */
    public function getListAccount(): array
    {
        return array_values($this->accounts);
    }

    public function save(RiotAccountEntity $updatedRiotAccount): void
    {
        $this->accounts[$updatedRiotAccount->getRiotID()] = $updatedRiotAccount; // écrase
    }
}
