<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\Sabotage;
use App\Domain\LanChallenge\SabotageLogInterface;

final class InMemorySabotageLog implements SabotageLogInterface
{
    /** @var array<int, Sabotage[]> sabotages enregistrés, par LAN */
    public array $recorded = [];

    public function record(int $lanId, Sabotage $sabotage): void
    {
        $this->recorded[$lanId][] = $sabotage;
    }
}
