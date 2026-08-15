<?php

namespace App\Infrastructure\RankedRace;

use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RankedRace\FirstRankedGameFinderInterface;
use App\Domain\RankedRace\RaceWindow;
use App\Domain\RankedRace\RankedGameStart;
use App\Enum\RiotApiEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineFirstRankedGameFinder implements FirstRankedGameFinderInterface
{
    /**
     * Sous ce seuil, la partie est une remake : aucun LP échangé, aucun enjeu.
     * Elle ne doit pas figer le départ d'une course pour toute la période.
     *
     * Cinq minutes et non trois : game_duration est stocké en minutes entières
     * (GameHistoryFactory divise les secondes), l'arrondi mange déjà une minute.
     */
    private const int REMAKE_MAX_MINUTES = 5;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findFirst(string $riotId, RankedQueueType $queue, RaceWindow $window): ?RankedGameStart
    {
        // On date par le DÉBUT de la partie (fin moins durée) parce que c'est ce
        // que « la course démarre au lancement de la première game » veut dire.
        // Le filtre porte en revanche sur la fin : une partie appartient à la
        // période où elle se termine, sinon une partie à cheval sur minuit
        // serait comptée deux fois.
        $sql = <<<'SQL'
            SELECT h.match_id,
                   DATE_SUB(h.date_game_end, INTERVAL h.game_duration MINUTE) AS started_at
            FROM history_account_lol h
            JOIN riot_account a ON a.id = h.riot_account_id
            WHERE a.riot_id = :riotId
              AND h.queue_id = :queueId
              AND h.date_game_end >= :start
              AND h.date_game_end < :end
              AND h.game_duration >= :minDuration
            ORDER BY started_at ASC
            LIMIT 1
            SQL;

        $row = $this->entityManager->getConnection()->executeQuery($sql, [
            'riotId' => $riotId,
            'queueId' => RiotApiEnum::matchQueueIdFor($queue),
            'start' => $window->startsAt,
            'end' => $window->endsAt,
            'minDuration' => self::REMAKE_MAX_MINUTES,
        ], [
            'riotId' => Types::STRING,
            'queueId' => Types::INTEGER,
            'start' => Types::DATETIME_IMMUTABLE,
            'end' => Types::DATETIME_IMMUTABLE,
            'minDuration' => Types::INTEGER,
        ])->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return new RankedGameStart(
            (string) $row['match_id'],
            new \DateTimeImmutable((string) $row['started_at']),
        );
    }
}
