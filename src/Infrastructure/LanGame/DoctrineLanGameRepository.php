<?php

namespace App\Infrastructure\LanGame;

use App\Domain\LanGame\LanGame;
use App\Domain\LanGame\LanGameRepositoryInterface;
use App\Domain\LanGame\LanGameStatus;
use App\Entity\AgentReport;
use App\Entity\LanEvent;
use App\Entity\LanGameRecord;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLanGameRepository implements LanGameRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(LanGame $game): void
    {
        $row = $this->entityManager->find(LanGameRecord::class, $game->gameId);
        if ($row === null) {
            $row = new LanGameRecord(
                $game->gameId,
                $this->entityManager->getReference(LanEvent::class, $game->lanId),
                $game->gameMode,
                $game->playersFingerprint,
                $game->estimatedStartAt,
            );
            $this->entityManager->persist($row);
        }

        $row->update($game->status, $game->lastReportAt, $game->settledScores);
        $this->entityManager->flush();
    }

    public function findCandidates(int $lanId, string $gameMode, string $playersFingerprint): array
    {
        $rows = $this->entityManager->getRepository(LanGameRecord::class)->findBy(
            ['lanEvent' => $lanId, 'gameMode' => $gameMode, 'playersFingerprint' => $playersFingerprint],
            ['estimatedStartAt' => 'DESC'],
        );

        return array_map(self::toDomain(...), $rows);
    }

    public function findBySession(string $tokenDigest, string $sessionId): ?LanGame
    {
        $report = $this->entityManager->getRepository(AgentReport::class)
            ->findOneBy(['tokenDigest' => $tokenDigest, 'sessionId' => $sessionId]);

        return $report === null ? null : self::toDomain($report->getGame());
    }

    public function findInProgress(): array
    {
        $rows = $this->entityManager->getRepository(LanGameRecord::class)->findBy(['status' => LanGameStatus::IN_PROGRESS]);

        return array_map(self::toDomain(...), $rows);
    }

    private static function toDomain(LanGameRecord $row): LanGame
    {
        return LanGame::restore(
            $row->getGameId(),
            $row->getLanEvent()->getId(),
            $row->getGameMode(),
            $row->getPlayersFingerprint(),
            $row->getEstimatedStartAt(),
            $row->getStatus(),
            $row->getLastReportAt(),
            $row->getSettledScores(),
        );
    }
}
