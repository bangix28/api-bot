<?php

namespace App\Infrastructure\LanChallenge;

use App\Domain\LanChallenge\LanSummary;
use App\Domain\LanChallenge\LanSummaryReaderInterface;
use App\Entity\LanEvent;
use App\Entity\RiotAccount;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLanSummaryReader implements LanSummaryReaderInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(int $lanId): ?LanSummary
    {
        $row = $this->entityManager->find(LanEvent::class, $lanId);
        if ($row === null) {
            return null;
        }

        $participants = [];
        foreach ($row->getParticipants() as $account) {
            /** @var RiotAccount $account */
            // Un compte jamais rafraîchi n'a pas de puuid : il ne peut pas encore jouer.
            if (($account->getPuuid() ?? '') !== '') {
                $participants[$account->getPuuid()] = $account->getRiotId();
            }
        }

        return new LanSummary($row->getId(), $row->getName(), $row->getStartsAt(), $row->getEndsAt(), $participants);
    }
}
