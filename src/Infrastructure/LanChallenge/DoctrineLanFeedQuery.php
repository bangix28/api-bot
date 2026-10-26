<?php

namespace App\Infrastructure\LanChallenge;

use App\Domain\LanChallenge\Challenge;
use App\Domain\LanChallenge\FeedEntry;
use App\Domain\LanChallenge\LanFeedQueryInterface;
use App\Domain\LanChallenge\RoundResult;
use App\Domain\LanChallenge\Sabotage;
use App\Entity\LanRoundResult;
use App\Entity\LanSabotage;
use App\Entity\RiotAccount;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fil d'exploits : manches réglées et sabotages visibles, mêlés par date.
 * Une Malédiction n'y figure jamais en tant que sabotage : la cible la
 * découvre au règlement de son défi (curseApplied).
 */
final readonly class DoctrineLanFeedQuery implements LanFeedQueryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return FeedEntry[] */
    public function latest(int $lanId, int $limit): array
    {
        $rounds = $this->latestRounds($lanId, $limit);
        $sabotages = $this->latestVisibleSabotages($lanId, $limit);

        $riotIds = $this->riotIdsByPuuid([
            ...array_map(static fn(LanRoundResult $result) => $result->getRun()->getPuuid(), $rounds),
            ...array_map(static fn(LanSabotage $sabotage) => $sabotage->getTargetPuuid(), $sabotages),
        ]);
        $riotIdOf = static fn(string $puuid) => $riotIds[$puuid] ?? $puuid;

        $entries = [
            ...array_map(
                static fn(LanRoundResult $result) => FeedEntry::round(
                    $riotIdOf($result->getRun()->getPuuid()),
                    new RoundResult(
                        $result->getGameId(), $result->getCard(), $result->getVerdict(), $result->getPoints(),
                        $result->isRoundBonus(), $result->isCurseApplied(),
                    ),
                    $result->getSettledAt(),
                ),
                $rounds,
            ),
            ...array_map(
                static fn(LanSabotage $sabotage) => FeedEntry::sabotage($riotIdOf($sabotage->getTargetPuuid()), $sabotage->getCard(), $sabotage->getOccurredAt()),
                $sabotages,
            ),
        ];

        // Tri stable : à heure égale, l'ordre de lecture (déjà le plus récent d'abord) est conservé.
        usort($entries, static fn(FeedEntry $left, FeedEntry $right) => $right->occurredAt <=> $left->occurredAt);

        return array_slice($entries, 0, $limit);
    }

    /** @return LanRoundResult[] */
    private function latestRounds(int $lanId, int $limit): array
    {
        return $this->entityManager->createQueryBuilder()
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
    }

    /** @return LanSabotage[] */
    private function latestVisibleSabotages(int $lanId, int $limit): array
    {
        $visibleCards = array_values(array_filter(
            Challenge::cases(),
            static fn(Challenge $card) => $card->targetsPlayer() && (new Sabotage($card, 'saboteur', 'cible'))->isVisibleToTarget(),
        ));

        return $this->entityManager->createQueryBuilder()
            ->select('sabotage')
            ->from(LanSabotage::class, 'sabotage')
            ->where('IDENTITY(sabotage.lanEvent) = :lanId')
            ->andWhere('sabotage.card IN (:visibleCards)')
            ->setParameter('lanId', $lanId)
            ->setParameter('visibleCards', array_map(static fn(Challenge $card) => $card->value, $visibleCards))
            ->orderBy('sabotage.occurredAt', 'DESC')
            ->addOrderBy('sabotage.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
