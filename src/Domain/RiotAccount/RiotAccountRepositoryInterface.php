<?php

namespace App\Domain\RiotAccount;

interface RiotAccountRepositoryInterface
{
    /** @return RiotAccountEntity[] */
    public function getListAccount(): array;
    public function save(RiotAccountEntity $updatedRiotAccount): void;
}