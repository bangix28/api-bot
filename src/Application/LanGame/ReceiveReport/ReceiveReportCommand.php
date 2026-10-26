<?php

namespace App\Application\LanGame\ReceiveReport;

use App\Domain\Agent\AgentToken;
use App\Domain\LanGame\GameSnapshot;

final readonly class ReceiveReportCommand
{
    public function __construct(
        /** Jeton de l'agent qui envoie : il dit de quel joueur et de quelle LAN vient le relevé. */
        public AgentToken $token,
        /** Tiré par l'agent au début de chaque partie qu'il détecte. */
        public string $sessionId,
        public int $sequence,
        public bool $final,
        public GameSnapshot $snapshot,
        /** Relevé tel que reçu, conservé pour le relevé final (preuve). */
        public string $rawPayload,
    ) {
    }
}
