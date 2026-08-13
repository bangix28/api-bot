<?php

namespace App\Domain\RiotAccount;

interface RiotAccountRepositoryInterface
{
    /** @return RiotAccountEntity[] */
    public function getListAccount(): array;

    /** Le puuid est l'identité technique d'un compte : stable au changement de pseudo. */
    public function findByPuuid(string $puuid): ?RiotAccountEntity;

    public function save(RiotAccountEntity $updatedRiotAccount): void;
}