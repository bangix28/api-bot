<?php

namespace App\Infrastructure\RiotAccount;

use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Domain\RiotAccount\RiotAccountEntity;
use App\Domain\RiotAccount\RiotAccountNotExistException;
use App\Domain\RiotAccount\RiotAccountRepositoryInterface;
use App\Domain\RiotAccount\SummonerRankedEntity;
use App\Entity\RiotAccount;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineRiotAccountRepository implements RiotAccountRepositoryInterface
{

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function getListAccount(): array
    {
        $listAccount = $this->entityManager->getRepository(RiotAccount::class)->findAll();


        $listRiotAccountEntity = [];
        foreach ($listAccount as $riotAccount) {
            $riotAccountEntity = new RiotAccountEntity(
                $riotAccount->getRiotId(),
                $riotAccount->getPuuid(),
                $riotAccount->getSummonerName(),
                new SummonerRankedEntity(
                    RankedRank::fromString($riotAccount->getSummonerRankedSoloRank()),
                    RankedTier::fromString($riotAccount->getSummonerRankedSoloTier()),
                    (int)$riotAccount->getSummonerRankedSoloLeaguePoints(),
                    $riotAccount->getSummonerRankedSoloWins(),
                    (int)$riotAccount->getSummonerRankedSoloLosses()
                ),
                $riotAccount->getSummonerLevel(),
                $riotAccount->getLogoId(),
            );

            $listRiotAccountEntity[] = $riotAccountEntity;
        }

        return $listRiotAccountEntity;
    }

    public function save(RiotAccountEntity $updatedRiotAccount): void
    {
        $riotAccount = $this->entityManager
            ->getRepository(RiotAccount::class)
            ->findOneBy(
                [
                    'riotId' => $updatedRiotAccount->getRiotId()
                ]
            );

        if ($riotAccount === null)
        {
            throw new RiotAccountNotExistException();
        }

        $riotAccount->setSummonerName($updatedRiotAccount->getSummonerName())
            ->setSummonerLevel($updatedRiotAccount->getSummonerLevel())
            ->setLogoId($updatedRiotAccount->getLogoId())
            ->setSummonerRankedSoloRank($updatedRiotAccount->getRanked()->getSoloDivision()->value)
            ->setSummonerRankedSoloTier($updatedRiotAccount->getRanked()->getSoloTier()->value)
            ->setSummonerRankedSoloLeaguePoints((string)$updatedRiotAccount->getRanked()->getSoloLeaguePoints())
            ->setSummonerRankedSoloLosses((string)$updatedRiotAccount->getRanked()->getSoloLosses())
            ->setSummonerRankedSoloWins($updatedRiotAccount->getRanked()->getSoloWin())
            ->setScore($updatedRiotAccount->getRanked()->getScore())
            ->setLastUpdate(new \DateTime());

        $this->entityManager->flush();
    }
}