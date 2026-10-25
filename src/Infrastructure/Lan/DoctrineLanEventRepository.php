<?php

namespace App\Infrastructure\Lan;

use App\Domain\Lan\LanEvent;
use App\Domain\Lan\LanEventRepositoryInterface;
use App\Entity\LanEvent as LanEventRow;
use App\Entity\RiotAccount;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLanEventRepository implements LanEventRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return LanEvent[] */
    public function findInProgressAt(\DateTimeImmutable $instant): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('l', 'p')
            ->from(LanEventRow::class, 'l')
            ->leftJoin('l.participants', 'p')
            ->where(':instant BETWEEN l.startsAt AND l.endsAt')
            ->setParameter('instant', $instant)
            ->getQuery()
            ->getResult();

        return array_map(self::toDomain(...), $rows);
    }

    private static function toDomain(LanEventRow $row): LanEvent
    {
        return new LanEvent(
            $row->getId(),
            $row->getName(),
            $row->getStartsAt(),
            $row->getEndsAt(),
            // Un compte jamais rafraîchi n'a pas encore de puuid : rien à collecter.
            array_values(array_filter(
                $row->getParticipants()->map(static fn(RiotAccount $a) => $a->getPuuid())->toArray(),
            )),
        );
    }
}
