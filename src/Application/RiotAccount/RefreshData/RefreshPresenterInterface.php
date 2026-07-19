<?php

namespace App\Application\RiotAccount\RefreshData;

use App\Domain\RiotAccount\RiotAccountEntity;

interface RefreshPresenterInterface
{
    /** @param RiotAccountEntity[] $accounts */
    public function present(array $accounts): void;
}
