<?php

namespace App\Domain\Agent;

final readonly class DevicePollOutcome
{
    public function __construct(
        /** La demande, avec l'heure de cette interrogation retenue. */
        public DeviceAuthorization $authorization,
        public DevicePollStatus $status,
    ) {
    }
}
