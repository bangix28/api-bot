<?php

namespace App\Domain\LanChallenge;

interface LanSummaryReaderInterface
{
    public function find(int $lanId): ?LanSummary;

    /**
     * La LAN en cours à laquelle l'un de ces comptes participe, sinon la
     * prochaine ; null s'il n'y en a aucune.
     *
     * @param string[] $puuids
     */
    public function findCurrentOrNextFor(array $puuids, \DateTimeImmutable $now): ?LanSummary;
}
