<?php

namespace App\Infrastructure\LanGame;

use App\Domain\LanGame\AgentReportLogInterface;
use App\Entity\AgentReport;
use App\Entity\LanGameRecord;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAgentReportLog implements AgentReportLogInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function lastSequence(string $tokenDigest, string $sessionId): ?int
    {
        $lastSequence = $this->entityManager->createQueryBuilder()
            ->select('MAX(report.sequence)')
            ->from(AgentReport::class, 'report')
            ->where('report.tokenDigest = :tokenDigest')
            ->andWhere('report.sessionId = :sessionId')
            ->setParameter('tokenDigest', $tokenDigest)
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->getSingleScalarResult();

        return $lastSequence === null ? null : (int) $lastSequence;
    }

    public function lastReceivedAt(string $tokenDigest): ?\DateTimeImmutable
    {
        $report = $this->entityManager->getRepository(AgentReport::class)
            ->findOneBy(['tokenDigest' => $tokenDigest], ['receivedAt' => 'DESC']);

        return $report?->getReceivedAt();
    }

    public function record(
        string $tokenDigest,
        string $sessionId,
        int $sequence,
        string $gameId,
        \DateTimeImmutable $receivedAt,
        ?string $finalPayload,
    ): void {
        $this->entityManager->persist(new AgentReport(
            $tokenDigest,
            $sessionId,
            $sequence,
            $this->entityManager->getReference(LanGameRecord::class, $gameId),
            $receivedAt,
            $finalPayload,
        ));
        $this->entityManager->flush();
    }
}
