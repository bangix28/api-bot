<?php

namespace App\Enum;

use App\Domain\EloSnapshot\RankedQueueType;

enum RiotApiEnum: int
{
    case QUEUE_TYPE_RANKED_SOLO = 420;
    case QUEUE_TYPE_RANKED_FLEX = 440;
    case START_INDEX = 0;
    // Par file : deux appels match-ids ramènent donc jusqu'à 20 identifiants.
    case MATCH_COUNT_RETRIEVE = 10;

    /**
     * Riot désigne la même file de deux façons : une chaîne dans les « league
     * entries » (le rang) et un entier dans les matchs. La correspondance vit
     * ici, du côté qui parle à Riot — le domaine n'a pas à connaître 420 ni 440.
     */
    public static function matchQueueIdFor(RankedQueueType $queue): int
    {
        return match ($queue) {
            RankedQueueType::SOLO => self::QUEUE_TYPE_RANKED_SOLO->value,
            RankedQueueType::FLEX => self::QUEUE_TYPE_RANKED_FLEX->value,
        };
    }

    public static function queueFromMatchQueueId(int $queueId): ?RankedQueueType
    {
        return match ($queueId) {
            self::QUEUE_TYPE_RANKED_SOLO->value => RankedQueueType::SOLO,
            self::QUEUE_TYPE_RANKED_FLEX->value => RankedQueueType::FLEX,
            default => null,
        };
    }
}
