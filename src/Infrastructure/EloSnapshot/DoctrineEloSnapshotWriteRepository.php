<?php

namespace App\Infrastructure\EloSnapshot;

use App\Domain\EloSnapshot\EloSnapshot;
use App\Domain\EloSnapshot\EloSnapshotWriteRepositoryInterface;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Domain\RiotAccount\RiotAccountNotExistException;
use App\Entity\RiotAccount;
use App\Entity\SummonerEloSnapshot;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineEloSnapshotWriteRepository implements EloSnapshotWriteRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function lastFor(string $puuid, RankedQueueType $queue): ?EloSnapshot
    {
        $row = $this->entityManager->createQueryBuilder()
            ->select('snapshot')
            ->from(SummonerEloSnapshot::class, 'snapshot')
            ->join('snapshot.riotAccount', 'account')
            ->where('account.puuid = :puuid')
            ->andWhere('snapshot.queueType = :queue')
            ->orderBy('snapshot.capturedAt', 'DESC')
            ->setMaxResults(1)
            ->setParameter('puuid', $puuid)
            ->setParameter('queue', $queue->value)
            ->getQuery()
            ->getOneOrNullResult();

        return $row === null ? null : $this->toDomain($puuid, $row);
    }

    public function add(EloSnapshot $snapshot): void
    {
        $account = $this->entityManager
            ->getRepository(RiotAccount::class)
            ->findOneBy(['puuid' => $snapshot->puuid]);

        if ($account === null) {
            throw new RiotAccountNotExistException();
        }

        $row = new SummonerEloSnapshot();
        $row->setRiotAccount($account)
            ->setQueueType($snapshot->queue)
            ->setCapturedAt($snapshot->capturedAt)
            ->setTier($snapshot->ranked->getTier()->value)
            ->setDivision($snapshot->ranked->getDivision()->value)
            ->setLeaguePoints($snapshot->ranked->getLeaguePoints())
            ->setWins($snapshot->ranked->getWins())
            ->setLosses($snapshot->ranked->getLosses());

        $this->entityManager->persist($row);
        // Flush unitaire : un échec SQL ne condamne que ce point, pas tout le run
        // (limite EntityManagerClosed documentée dans l'ADR-0002).
        $this->entityManager->flush();
    }

    private function toDomain(string $puuid, SummonerEloSnapshot $row): EloSnapshot
    {
        return new EloSnapshot(
            $puuid,
            $row->getCapturedAt(),
            $row->getQueueType(),
            new RankedQueueEntity(
                RankedRank::fromString((string) $row->getDivision()),
                RankedTier::fromString((string) $row->getTier()),
                (int) $row->getLeaguePoints(),
                (int) $row->getWins(),
                (int) $row->getLosses(),
            ),
        );
    }
}
