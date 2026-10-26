<?php

namespace App\Application\Agent\ExchangeDeviceCode;

use App\Application\Agent\AgentPairingView;
use App\Domain\Agent\AgentToken;

final readonly class IssuedAgentToken
{
    public function __construct(
        /** Valeur en clair, remise à l'agent cette seule fois. */
        public string $plainToken,
        public AgentToken $token,
        public AgentPairingView $pairing,
    ) {
    }
}
