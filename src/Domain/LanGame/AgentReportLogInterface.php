<?php

namespace App\Domain\LanGame;

/**
 * Trace des relevés reçus : idempotence (sequence), cadence (anti-rafale) et
 * preuve (le relevé final est conservé tel que reçu).
 */
interface AgentReportLogInterface
{
    public function lastSequence(string $tokenDigest, string $sessionId): ?int;

    public function lastReceivedAt(string $tokenDigest): ?\DateTimeImmutable;

    /**
     * @param string|null $finalPayload JSON brut du relevé final, null pour un relevé intermédiaire
     */
    public function record(
        string $tokenDigest,
        string $sessionId,
        int $sequence,
        string $gameId,
        \DateTimeImmutable $receivedAt,
        ?string $finalPayload,
    ): void;
}
