<?php

namespace App\Infrastructure\MatchHistory;

use App\Domain\MatchHistory\MatchHistoryRefreshTarget;
use App\Domain\MatchHistory\MatchHistoryRefreshTargetsProviderInterface;
use App\Entity\RiotAccount;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMatchHistoryRefreshTargetsProvider implements MatchHistoryRefreshTargetsProviderInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return MatchHistoryRefreshTarget[] */
    public function all(): array
    {
        return array_map(
            fn(RiotAccount $account) => new MatchHistoryRefreshTarget(
                $account->getPuuid(),
                $account->getLastUpdate()?->getTimestamp(),
            ),
            $this->entityManager->getRepository(RiotAccount::class)->findAll(),
        );
    }
}
