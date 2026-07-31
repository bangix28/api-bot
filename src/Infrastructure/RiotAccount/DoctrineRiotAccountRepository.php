<?php

namespace App\Infrastructure\RiotAccount;

use App\Domain\RiotAccount\MiniSeries;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Domain\RiotAccount\RiotAccountEntity;
use App\Domain\RiotAccount\RiotAccountNotExistException;
use App\Domain\RiotAccount\RiotAccountRepositoryInterface;
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
                new RankedQueueEntity(
                    RankedRank::fromString($riotAccount->getSummonerRankedSoloRank()),
                    RankedTier::fromString($riotAccount->getSummonerRankedSoloTier()),
                    (int)$riotAccount->getSummonerRankedSoloLeaguePoints(),
                    $riotAccount->getSummonerRankedSoloWins(),
                    (int)$riotAccount->getSummonerRankedSoloLosses(),
                    (bool)$riotAccount->getSoloHotStreak(),
                    (bool)$riotAccount->getSoloVeteran(),
                    (bool)$riotAccount->getSoloFreshBlood(),
                    $this->hydrateMiniSeries($riotAccount),
                ),
                $riotAccount->getSummonerLevel(),
                $riotAccount->getLogoId(),
                $this->hydrateFlex($riotAccount),
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

        $rankedSolo = $updatedRiotAccount->getRankedSolo();
        $rankedFlex = $updatedRiotAccount->getRankedFlex();
        $miniSeries = $rankedSolo->getMiniSeries();

        $riotAccount->setSummonerName($updatedRiotAccount->getSummonerName())
            ->setSummonerLevel($updatedRiotAccount->getSummonerLevel())
            ->setLogoId($updatedRiotAccount->getLogoId())
            ->setSummonerRankedSoloRank($rankedSolo->getDivision()->value)
            ->setSummonerRankedSoloTier($rankedSolo->getTier()->value)
            ->setSummonerRankedSoloLeaguePoints((string)$rankedSolo->getLeaguePoints())
            ->setSummonerRankedSoloLosses((string)$rankedSolo->getLosses())
            ->setSummonerRankedSoloWins($rankedSolo->getWins())
            ->setSoloHotStreak($rankedSolo->isHotStreak())
            ->setSoloVeteran($rankedSolo->isVeteran())
            ->setSoloFreshBlood($rankedSolo->isFreshBlood())
            ->setSoloMiniSeriesWins($miniSeries?->wins)
            ->setSoloMiniSeriesLosses($miniSeries?->losses)
            ->setSoloMiniSeriesTarget($miniSeries?->target)
            ->setSoloMiniSeriesProgress($miniSeries?->progress)
            ->setSummonerRankedFlexTier($rankedFlex?->getTier()->value)
            ->setSummonerRankedFlexRank($rankedFlex?->getDivision()->value)
            ->setSummonerRankedFlexLeaguePoints($rankedFlex?->getLeaguePoints())
            ->setSummonerRankedFlexWins($rankedFlex?->getWins())
            ->setSummonerRankedFlexLosses($rankedFlex?->getLosses())
            ->setScore($rankedSolo->getScore())
            ->setLastUpdate(new \DateTime());

        $this->entityManager->flush();
    }

    private function hydrateMiniSeries(RiotAccount $riotAccount): ?MiniSeries
    {
        if ($riotAccount->getSoloMiniSeriesTarget() === null) {
            return null;
        }

        return new MiniSeries(
            (int)$riotAccount->getSoloMiniSeriesWins(),
            (int)$riotAccount->getSoloMiniSeriesLosses(),
            $riotAccount->getSoloMiniSeriesTarget(),
            (string)$riotAccount->getSoloMiniSeriesProgress(),
        );
    }

    private function hydrateFlex(RiotAccount $riotAccount): ?RankedQueueEntity
    {
        // Colonnes flex à null = compte jamais classé en Flex.
        if ($riotAccount->getSummonerRankedFlexTier() === null) {
            return null;
        }

        return new RankedQueueEntity(
            RankedRank::fromString($riotAccount->getSummonerRankedFlexRank()),
            RankedTier::fromString($riotAccount->getSummonerRankedFlexTier()),
            (int)$riotAccount->getSummonerRankedFlexLeaguePoints(),
            (int)$riotAccount->getSummonerRankedFlexWins(),
            (int)$riotAccount->getSummonerRankedFlexLosses(),
        );
    }
}
