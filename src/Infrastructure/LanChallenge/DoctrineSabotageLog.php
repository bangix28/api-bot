<?php

namespace App\Infrastructure\LanChallenge;

use App\Domain\LanChallenge\Sabotage;
use App\Domain\LanChallenge\SabotageLogInterface;
use App\Domain\Shared\ClockInterface;
use App\Entity\LanEvent;
use App\Entity\LanSabotage;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSabotageLog implements SabotageLogInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function record(int $lanId, Sabotage $sabotage): void
    {
        $this->entityManager->persist(new LanSabotage(
            $this->entityManager->getReference(LanEvent::class, $lanId),
            $sabotage->card,
            $sabotage->saboteurPuuid,
            $sabotage->targetPuuid,
            $this->clock->now(),
        ));
        $this->entityManager->flush();
    }
}
