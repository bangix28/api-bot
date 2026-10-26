<?php

namespace App\Infrastructure\LanChallenge;

use App\Domain\LanChallenge\FeedEntry;
use App\Domain\LanChallenge\LanFeedQueryInterface;
use App\Entity\LanRoundResult;
use App\Entity\RiotAccount;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLanFeedQuery implements LanFeedQueryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return FeedEntry[] */
    public function latest(int $lanId, int $limit): array
    {
        /** @var LanRoundResult[] $results */
        $results = $this->entityManager->createQueryBuilder()
            ->select('result', 'run')
            ->from(LanRoundResult::class, 'result')
            ->join('result.run', 'run')
            ->where('IDENTITY(run.lanEvent) = :lanId')
            ->setParameter('lanId', $lanId)
            // L'id départage les manches réglées dans la même seconde (même partie).
            ->orderBy('result.settledAt', 'DESC')
            ->addOrderBy('result.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $riotIds = $this->riotIdsByPuuid(array_map(static fn(LanRoundResult $result) => $result->getRun()->getPuuid(), $results));

        return array_map(
            static fn(LanRoundResult $result) => new FeedEntry(
                $riotIds[$result->getRun()->getPuuid()] ?? $result->getRun()->getPuuid(),
                $result->getGameId(),
                $result->getCard(),
                $result->getVerdict(),
                $result->getPoints(),
                $result->isRoundBonus(),
                $result->getSettledAt(),
            ),
            $results,
        );
    }

    /**
     * @param string[] $puuids
     * @return array<string, string>
     */
    private function riotIdsByPuuid(array $puuids): array
    {
        if ($puuids === []) {
            return [];
        }

        $riotIds = [];
        foreach ($this->entityManager->getRepository(RiotAccount::class)->findBy(['puuid' => array_unique($puuids)]) as $account) {
            $riotIds[$account->getPuuid()] = $account->getRiotId();
        }

        return $riotIds;
    }
}
