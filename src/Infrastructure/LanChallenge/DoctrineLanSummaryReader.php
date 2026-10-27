<?php

namespace App\Infrastructure\LanChallenge;

use App\Domain\LanChallenge\LanQueue;
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

        return $row === null ? null : self::toDomain($row);
    }

    public function findCurrentOrNextFor(array $puuids, \DateTimeImmutable $now): ?LanSummary
    {
        if ($puuids === []) {
            return null;
        }

        // Pas encore terminée, et la plus tôt commencée : la LAN en cours passe
        // devant la prochaine.
        $row = $this->entityManager->createQueryBuilder()
            ->select('lan')
            ->from(LanEvent::class, 'lan')
            ->join('lan.participants', 'participant')
            ->where('participant.puuid IN (:puuids)')
            ->andWhere('lan.endsAt >= :now')
            ->setParameter('puuids', $puuids)
            ->setParameter('now', $now)
            ->orderBy('lan.startsAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $row === null ? null : self::toDomain($row);
    }

    private static function toDomain(LanEvent $row): LanSummary
    {
        $participants = [];
        foreach ($row->getParticipants() as $account) {
            /** @var RiotAccount $account */
            // Un compte jamais rafraîchi n'a pas de puuid : il ne peut pas encore jouer.
            if (($account->getPuuid() ?? '') !== '') {
                $participants[$account->getPuuid()] = $account->getRiotId();
            }
        }

        // Une file retirée du catalogue depuis l'enregistrement de la LAN est ignorée.
        $acceptedQueues = array_values(array_filter(array_map(LanQueue::tryFrom(...), $row->getAcceptedQueueIds())));

        return new LanSummary($row->getId(), $row->getName(), $row->getStartsAt(), $row->getEndsAt(), $participants, $acceptedQueues);
    }
}
