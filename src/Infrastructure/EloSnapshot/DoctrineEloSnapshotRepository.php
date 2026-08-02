<?php

namespace App\Infrastructure\EloSnapshot;

use App\Domain\EloSnapshot\DailyEloSnapshot;
use App\Domain\EloSnapshot\EloSnapshotRepositoryInterface;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RankedRace\RacePlayer;
use App\Domain\RankedRace\RaceSnapshot;
use App\Domain\RankedRace\RaceSnapshotRepositoryInterface;
use App\Domain\RankedRace\RaceWindow;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Domain\RiotAccount\RiotAccountNotExistException;
use App\Entity\RiotAccount;
use App\Entity\SummonerEloDaily;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineEloSnapshotRepository implements EloSnapshotRepositoryInterface, RaceSnapshotRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function existsFor(string $puuid, \DateTimeImmutable $day): bool
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(snapshot.id)')
            ->from(SummonerEloDaily::class, 'snapshot')
            ->join('snapshot.riotAccount', 'account')
            ->where('account.puuid = :puuid')
            ->andWhere('snapshot.dateScore = :day')
            ->setParameter('puuid', $puuid)
            // Type DATE explicite : la colonne est une DATE, un datetime implicite
            // (avec heure) ne matcherait que les snapshots pris à minuit pile.
            ->setParameter('day', $day, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function add(DailyEloSnapshot $snapshot): void
    {
        $account = $this->entityManager
            ->getRepository(RiotAccount::class)
            ->findOneBy(['puuid' => $snapshot->puuid]);

        if ($account === null) {
            throw new RiotAccountNotExistException();
        }

        $dailyElo = new SummonerEloDaily();
        $dailyElo->setRiotAccount($account)
            // La colonne est DATE_MUTABLE : DBAL 4 refuse un DateTimeImmutable à l'écriture.
            ->setDateScore(\DateTime::createFromImmutable($snapshot->day))
            ->setQueueType($snapshot->queue)
            // score aplati conservé : c'est le contrat JSON de la courbe /elo-daily.
            ->setScore((string) $snapshot->ranked->getScore())
            ->setTier($snapshot->ranked->getTier()->value)
            ->setDivision($snapshot->ranked->getDivision()->value)
            ->setLeaguePoints($snapshot->ranked->getLeaguePoints())
            ->setWins($snapshot->ranked->getWins())
            ->setLosses($snapshot->ranked->getLosses());

        $this->entityManager->persist($dailyElo);
        // Flush unitaire : un échec SQL ne condamne que ce snapshot, pas tout le run
        // (limite EntityManagerClosed documentée dans l'ADR-0002).
        $this->entityManager->flush();
    }

    public function findForWindow(RankedQueueType $queue, RaceWindow $window): array
    {
        /** @var SummonerEloDaily[] $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('snapshot', 'account')
            ->from(SummonerEloDaily::class, 'snapshot')
            ->join('snapshot.riotAccount', 'account')
            ->where('snapshot.queueType = :queue')
            ->andWhere('snapshot.dateScore BETWEEN :start AND :end')
            // Lignes historiques (avant la Ranked Race) : score aplati sans détail
            // de rang ni wins/losses -> inexploitables pour la course.
            ->andWhere('snapshot.tier IS NOT NULL')
            ->orderBy('account.riotId', 'ASC')
            ->addOrderBy('snapshot.dateScore', 'ASC')
            ->setParameter('queue', $queue->value)
            ->setParameter('start', $window->start, Types::DATE_IMMUTABLE)
            ->setParameter('end', $window->end, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn(SummonerEloDaily $row) => new RaceSnapshot(
                new RacePlayer(
                    (string) $row->getRiotAccount()->getRiotId(),
                    (string) $row->getRiotAccount()->getSummonerName(),
                    (string) $row->getRiotAccount()->getLogoId(),
                ),
                \DateTimeImmutable::createFromInterface($row->getDateScore()),
                new RankedQueueEntity(
                    RankedRank::fromString((string) $row->getDivision()),
                    RankedTier::fromString((string) $row->getTier()),
                    (int) $row->getLeaguePoints(),
                    (int) $row->getWins(),
                    (int) $row->getLosses(),
                ),
            ),
            $rows,
        );
    }
}
