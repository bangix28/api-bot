<?php

namespace App\Infrastructure\MatchHistory;

use App\Domain\MatchHistory\GameHistoryEntity;
use App\Domain\MatchHistory\MatchHistoryRepositoryInterface;
use App\Domain\RiotAccount\RiotAccountEntity;
use App\Domain\RiotAccount\RiotAccountNotExistException;
use App\Entity\HistoryAccountLol;
use App\Entity\RiotAccount;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineMatchHistoryRepository implements MatchHistoryRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(GameHistoryEntity $gameHistoryEntity): void
    {
        $riotAccount = $this->entityManager
            ->getRepository(RiotAccount::class)
                ->findOneBy(
                    ['puuid' => $gameHistoryEntity->puuid]
                );

        if ($riotAccount === null)
        {
            throw new RiotAccountNotExistException();
        }

        $history = new HistoryAccountLol()
            ->setRiotAccount($riotAccount)
            ->setIsWin($gameHistoryEntity->isWin)
            ->setChampionId($gameHistoryEntity->championId)
            ->setKillPlayer($gameHistoryEntity->kills)
            ->setDeathPlayer($gameHistoryEntity->deaths)
            ->setAssistPlayer($gameHistoryEntity->assists)
            ->setDateGameEnd($gameHistoryEntity->gameEnd)
            ->setGameDuration((string) $gameHistoryEntity->gameDuration)   // colonne string
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($history);   // entité NOUVELLE → persist (comme pour User)
        $this->entityManager->flush();
    }
}