<?php

namespace App\Application\Agent\RequestDeviceCode;

final readonly class DeviceCodeView
{
    public function __construct(
        /** À afficher au joueur, au format « K7QF-92PZ ». */
        public string $userCode,
        /** Secret de l'agent, à ne jamais afficher. */
        public string $deviceCode,
        public int $expiresInSeconds,
        public int $pollIntervalSeconds,
    ) {
    }
}
