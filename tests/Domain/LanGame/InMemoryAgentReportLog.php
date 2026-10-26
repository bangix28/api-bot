<?php

namespace App\Tests\Domain\LanGame;

use App\Domain\LanGame\AgentReportLogInterface;

final class InMemoryAgentReportLog implements AgentReportLogInterface
{
    /** @var list<array{tokenDigest: string, sessionId: string, sequence: int, gameId: string, receivedAt: \DateTimeImmutable, finalPayload: ?string}> */
    public array $records = [];

    public function lastSequence(string $tokenDigest, string $sessionId): ?int
    {
        $sequences = array_column($this->recordsOf($tokenDigest, $sessionId), 'sequence');

        return $sequences === [] ? null : max($sequences);
    }

    public function lastReceivedAt(string $tokenDigest): ?\DateTimeImmutable
    {
        $receptions = array_column(array_filter($this->records, static fn(array $record) => $record['tokenDigest'] === $tokenDigest), 'receivedAt');

        return $receptions === [] ? null : max($receptions);
    }

    public function record(
        string $tokenDigest,
        string $sessionId,
        int $sequence,
        string $gameId,
        \DateTimeImmutable $receivedAt,
        ?string $finalPayload,
    ): void {
        $this->records[] = compact('tokenDigest', 'sessionId', 'sequence', 'gameId', 'receivedAt', 'finalPayload');
    }

    public function gameIdOf(string $tokenDigest, string $sessionId): ?string
    {
        return $this->recordsOf($tokenDigest, $sessionId)[0]['gameId'] ?? null;
    }

    /** @return list<array{tokenDigest: string, sessionId: string, sequence: int, gameId: string, receivedAt: \DateTimeImmutable, finalPayload: ?string}> */
    private function recordsOf(string $tokenDigest, string $sessionId): array
    {
        return array_values(array_filter(
            $this->records,
            static fn(array $record) => $record['tokenDigest'] === $tokenDigest && $record['sessionId'] === $sessionId,
        ));
    }
}
