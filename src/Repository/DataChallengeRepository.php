<?php

namespace App\Repository;

use App\Entity\DataChallenge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DataChallenge>
 */
class DataChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DataChallenge::class);
    }

    public  function findChallengeByQueue(int $queueType)
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.challenge')
            ->where('c.queueType = :queue')
            ->setParameter('queue', $queueType)
            ->getQuery();

        $results = $qb->getResult();
        return array_column($results, 'challenge');

    }

    //    /**
    //     * @return DataChallenge[] Returns an array of DataChallenge objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?DataChallenge
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
