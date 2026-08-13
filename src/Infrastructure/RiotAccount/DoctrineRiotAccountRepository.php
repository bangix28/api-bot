<?php

namespace App\Infrastructure\RiotAccount;

use App\Domain\RiotAccount\RiotAccountEntity;
use App\Domain\RiotAccount\RiotAccountNotExistException;
use App\Domain\RiotAccount\RiotAccountRepositoryInterface;
use App\Entity\RiotAccount;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DoctrineRiotAccountRepository implements RiotAccountRepositoryInterface
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $refreshLogger = new NullLogger(),
    )
    {
    }

    public function getListAccount(): array
    {
        $listAccount = $this->entityManager->getRepository(RiotAccount::class)->findAll();

        $listRiotAccountEntity = [];
        foreach ($listAccount as $riotAccount) {
            // Sans puuid (aucun appel Riot possible) ni riotId (clé de save()),
            // la ligne est inexploitable : on la signale et on passe.
            if (($riotAccount->getPuuid() ?? '') === '' || ($riotAccount->getRiotId() ?? '') === '') {
                $this->refreshLogger->warning('Compte ignoré : identité incomplète en base', [
                    'id' => $riotAccount->getId(),
                    'riotId' => $riotAccount->getRiotId(),
                ]);

                continue;
            }

            try {
                $listRiotAccountEntity[] = RiotAccountRowMapper::map($riotAccount);
            } catch (\Throwable $exception) {
                // \Throwable et non \Exception : une valeur aberrante en base produit
                // un TypeError (qui étend Error) ou une exception de validation du
                // domaine. Sans ce filet, une seule ligne corrompue casse la lecture
                // pour tous les comptes — donc les crons refreshSummoners et daily-elo
                // en entier (écart assumé à l'ADR-0002, qui isole les échecs par compte).
                $this->refreshLogger->warning('Compte ignoré : ligne illisible en base', [
                    'id' => $riotAccount->getId(),
                    'riotId' => $riotAccount->getRiotId(),
                    'exception' => $exception,
                ]);
            }
        }

        return $listRiotAccountEntity;
    }

    public function findByPuuid(string $puuid): ?RiotAccountEntity
    {
        $riotAccount = $this->entityManager
            ->getRepository(RiotAccount::class)
            ->findOneBy(['puuid' => $puuid]);

        if ($riotAccount === null) {
            return null;
        }

        // Pas de catch ici, contrairement à getListAccount() : la lecture d'un compte
        // précis répond à une action synchrone, l'appelant doit voir l'échec.
        return RiotAccountRowMapper::map($riotAccount);
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
}
