<?php

namespace App\Domain\Lan;

interface LanEventRepositoryInterface
{
    /** @return LanEvent[] LAN dont la fenêtre contient l'instant donné */
    public function findInProgressAt(\DateTimeImmutable $instant): array;
}
