<?php

namespace App\Infrastructure\EloSnapshot;

use App\Domain\EloSnapshot\DailyEloSnapshot;
use App\Domain\EloSnapshot\EloSnapshotRepositoryInterface;
use App\Domain\RiotAccount\RiotAccountNotExistException;
use App\Entity\RiotAccount;
use App\Entity\SummonerEloDaily;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Maille quotidienne, désormais dédiée à la seule courbe publique /elo-daily.
 * La course lit summoner_elo_snapshot via DoctrineRaceSnapshotRepository.
 */
class DoctrineEloSnapshotRepository implements EloSnapshotRepositoryInterface
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

}
