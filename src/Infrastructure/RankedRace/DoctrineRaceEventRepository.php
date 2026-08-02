<?php

namespace App\Infrastructure\RankedRace;

use App\Domain\RankedRace\RaceEvent;
use App\Domain\RankedRace\RaceEventRepositoryInterface;
use App\Domain\RankedRace\RaceWindow;
use App\Entity\RankedRaceEvent;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineRaceEventRepository implements RaceEventRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findAll(): array
    {
        $rows = $this->entityManager
            ->getRepository(RankedRaceEvent::class)
            ->findBy([], ['startDate' => 'DESC', 'id' => 'DESC']);

        return array_map($this->toDomain(...), $rows);
    }

    public function findById(int $id): ?RaceEvent
    {
        $row = $this->entityManager->find(RankedRaceEvent::class, $id);

        return $row === null ? null : $this->toDomain($row);
    }

    private function toDomain(RankedRaceEvent $row): RaceEvent
    {
        return new RaceEvent(
            (int) $row->getId(),
            (string) $row->getName(),
            $row->getQueueType(),
            new RaceWindow(
                \DateTimeImmutable::createFromInterface($row->getStartDate()),
                \DateTimeImmutable::createFromInterface($row->getEndDate()),
            ),
            (int) $row->getMinGamesToQualify(),
        );
    }
}
