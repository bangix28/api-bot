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
            ->setMatchId($gameHistoryEntity->matchId)
            ->setQueueId($gameHistoryEntity->queueId)
            ->setIsWin($gameHistoryEntity->isWin)
            ->setChampionId($gameHistoryEntity->championId)
            ->setChampionName($gameHistoryEntity->championName)
            ->setTeamPosition($gameHistoryEntity->teamPosition)
            ->setKillPlayer($gameHistoryEntity->score->kills)
            ->setDeathPlayer($gameHistoryEntity->score->deaths)
            ->setAssistPlayer($gameHistoryEntity->score->assists)
            ->setChampLevel($gameHistoryEntity->score->champLevel)
            ->setGoldEarned($gameHistoryEntity->score->goldEarned)
            ->setCreepScore($gameHistoryEntity->score->creepScore)
            ->setVisionScore($gameHistoryEntity->score->visionScore)
            ->setItem0($gameHistoryEntity->build->items[0])
            ->setItem1($gameHistoryEntity->build->items[1])
            ->setItem2($gameHistoryEntity->build->items[2])
            ->setItem3($gameHistoryEntity->build->items[3])
            ->setItem4($gameHistoryEntity->build->items[4])
            ->setItem5($gameHistoryEntity->build->items[5])
            ->setItem6($gameHistoryEntity->build->items[6])
            ->setSummonerSpell1Id($gameHistoryEntity->build->summonerSpell1Id)
            ->setSummonerSpell2Id($gameHistoryEntity->build->summonerSpell2Id)
            ->setRuneKeystoneId($gameHistoryEntity->build->runes->keystoneId)
            ->setRunePrimaryStyleId($gameHistoryEntity->build->runes->primaryStyleId)
            ->setRuneSubStyleId($gameHistoryEntity->build->runes->subStyleId)
            ->setRuneStatDefense($gameHistoryEntity->build->runes->statDefense)
            ->setRuneStatFlex($gameHistoryEntity->build->runes->statFlex)
            ->setRuneStatOffense($gameHistoryEntity->build->runes->statOffense)
            ->setTotalDamageDealtToChampions($gameHistoryEntity->combat->totalDamageDealtToChampions)
            ->setTotalDamageTaken($gameHistoryEntity->combat->totalDamageTaken)
            ->setDoubleKills($gameHistoryEntity->combat->doubleKills)
            ->setTripleKills($gameHistoryEntity->combat->tripleKills)
            ->setQuadraKills($gameHistoryEntity->combat->quadraKills)
            ->setPentaKills($gameHistoryEntity->combat->pentaKills)
            ->setFirstBloodKill($gameHistoryEntity->combat->firstBloodKill)
            ->setGameEndedInSurrender($gameHistoryEntity->combat->gameEndedInSurrender)
            ->setKda($gameHistoryEntity->performance->kda)
            ->setKillParticipation($gameHistoryEntity->performance->killParticipation)
            ->setDamagePerMinute($gameHistoryEntity->performance->damagePerMinute)
            ->setGoldPerMinute($gameHistoryEntity->performance->goldPerMinute)
            ->setVisionScorePerMinute($gameHistoryEntity->performance->visionScorePerMinute)
            ->setDateGameEnd($gameHistoryEntity->gameEnd)
            ->setGameDuration($gameHistoryEntity->gameDuration)
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($history);   // entité NOUVELLE → persist (comme pour User)
        $this->entityManager->flush();
    }

    public function exists(string $matchId, string $puuid): bool
    {
        // Le exists() en amont est le mécanisme principal d'idempotence : la contrainte
        // unique DB n'est qu'un filet (une UniqueConstraintViolationException fermerait
        // l'EntityManager et tuerait les flushs suivants de la boucle de refresh).
        return null !== $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(HistoryAccountLol::class, 'h')
            ->join('h.riotAccount', 'a')
            ->where('h.matchId = :matchId')
            ->andWhere('a.puuid = :puuid')
            ->setParameter('matchId', $matchId)
            ->setParameter('puuid', $puuid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
