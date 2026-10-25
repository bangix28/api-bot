<?php

namespace App\Domain\MatchHistory;

use App\Domain\EloSnapshot\RankedQueueType;

/**
 * Files de matchs collectées, valeur = queueId Riot
 * (https://static.developer.riotgames.com/docs/lol/queues.json).
 *
 * Distinct de RankedQueueType : celui-ci désigne un classement League-V4,
 * celui-là une file Match-V5. La collecte des LAN porte sur une file qui n'a
 * pas de classement (ARAM : Chaos), d'où ce second type.
 */
enum MatchQueue: int
{
    case RANKED_SOLO = 420;
    case RANKED_FLEX = 440;
    /** « ARAM: Mayhem » dans queues.json. */
    case ARAM_MAYHEM = 2400;

    public static function fromRanked(RankedQueueType $queue): self
    {
        return match ($queue) {
            RankedQueueType::SOLO => self::RANKED_SOLO,
            RankedQueueType::FLEX => self::RANKED_FLEX,
        };
    }

    /**
     * Files de la collecte historique : la course suit les deux files classées.
     *
     * @return self[]
     */
    public static function ranked(): array
    {
        return array_map(self::fromRanked(...), RankedQueueType::cases());
    }
}
