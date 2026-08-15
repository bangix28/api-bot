<?php

namespace App\Infrastructure\RankedRace;

use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RankedRace\RacePlayer;
use App\Domain\RankedRace\RaceSnapshot;
use App\Domain\RankedRace\RaceSnapshotRepositoryInterface;
use App\Domain\RankedRace\RaceWindow;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Entity\SummonerEloSnapshot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineRaceSnapshotRepository implements RaceSnapshotRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findForWindow(RankedQueueType $queue, RaceWindow $window): array
    {
        $snapshots = [...$this->carryIn($queue, $window), ...$this->insideWindow($queue, $window)];

        usort(
            $snapshots,
            static fn(RaceSnapshot $a, RaceSnapshot $b) => [$a->player->riotId, $a->capturedAt]
                <=> [$b->player->riotId, $b->capturedAt],
        );

        return $snapshots;
    }

    /** @return RaceSnapshot[] */
    private function insideWindow(RankedQueueType $queue, RaceWindow $window): array
    {
        /** @var SummonerEloSnapshot[] $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('snapshot', 'account')
            ->from(SummonerEloSnapshot::class, 'snapshot')
            ->join('snapshot.riotAccount', 'account')
            ->where('snapshot.queueType = :queue')
            ->andWhere('snapshot.capturedAt >= :start')
            ->andWhere('snapshot.capturedAt < :end')
            ->setParameter('queue', $queue->value)
            ->setParameter('start', $window->startsAt, Types::DATETIME_IMMUTABLE)
            ->setParameter('end', $window->endsAt, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn(SummonerEloSnapshot $row) => new RaceSnapshot(
                new RacePlayer(
                    (string) $row->getRiotAccount()->getRiotId(),
                    (string) $row->getRiotAccount()->getSummonerName(),
                    (string) $row->getRiotAccount()->getLogoId(),
                ),
                $row->getCapturedAt(),
                new RankedQueueEntity(
                    RankedRank::fromString((string) $row->getDivision()),
                    RankedTier::fromString((string) $row->getTier()),
                    (int) $row->getLeaguePoints(),
                    (int) $row->getWins(),
                    (int) $row->getLosses(),
                ),
            ),
            $rows,
        );
    }

    /**
     * Report : pour chaque joueur, son dernier relevé AVANT la fenêtre.
     *
     * Sans lui, un joueur dont le rang n'a pas bougé depuis des jours n'aurait
     * aucun rang de départ, et sa première partie de la période — celle qui
     * fait justement démarrer sa course — serait invisible.
     *
     * SQL natif : DQL n'a pas de fonction fenêtre, et un GROUP BY suivi d'une
     * seconde requête coûterait un aller-retour de plus par joueur.
     *
     * @return RaceSnapshot[]
     */
    private function carryIn(RankedQueueType $queue, RaceWindow $window): array
    {
        $sql = <<<'SQL'
            SELECT s.captured_at, s.tier, s.division, s.league_points, s.wins, s.losses,
                   a.riot_id, a.summoner_name, a.logo_id
            FROM (
                SELECT s.*,
                       ROW_NUMBER() OVER (PARTITION BY s.riot_account_id ORDER BY s.captured_at DESC) AS rn
                FROM summoner_elo_snapshot s
                WHERE s.queue_type = :queue
                  AND s.captured_at < :start
                  AND s.captured_at >= :carryInStart
            ) s
            JOIN riot_account a ON a.id = s.riot_account_id
            WHERE s.rn = 1
            SQL;

        $rows = $this->entityManager->getConnection()->executeQuery($sql, [
            'queue' => $queue->value,
            'start' => $window->startsAt,
            'carryInStart' => $window->carryInStart(),
        ], [
            'queue' => Types::STRING,
            'start' => Types::DATETIME_IMMUTABLE,
            'carryInStart' => Types::DATETIME_IMMUTABLE,
        ])->fetchAllAssociative();

        return array_map(
            static fn(array $row) => new RaceSnapshot(
                new RacePlayer(
                    (string) $row['riot_id'],
                    (string) $row['summoner_name'],
                    (string) $row['logo_id'],
                ),
                new \DateTimeImmutable((string) $row['captured_at']),
                new RankedQueueEntity(
                    RankedRank::fromString((string) $row['division']),
                    RankedTier::fromString((string) $row['tier']),
                    (int) $row['league_points'],
                    (int) $row['wins'],
                    (int) $row['losses'],
                ),
            ),
            $rows,
        );
    }
}
