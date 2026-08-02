<?php

namespace App\Domain\RankedRace;

use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedTier;

/**
 * Score composite propre à la Ranked Race, distinct de RankedTier::getScore()
 * (utilisé par le classement « rang absolu », qu'on ne modifie pas).
 *
 * Règle Master+ : plancher unique à Master 0 LP + LP additionnés directement.
 * Le libellé GM/Challenger est cosmétique (même échelle de LP) : le compter
 * comme +1000 fabriquerait des deltas fantômes au moment de la promotion.
 */
final class RaceScore
{
    private const array APEX_TIERS = [
        RankedTier::MASTER,
        RankedTier::GRANDMASTER,
        RankedTier::CHALLENGER,
    ];

    public static function of(RankedQueueEntity $ranked): int
    {
        if (in_array($ranked->getTier(), self::APEX_TIERS, true)) {
            return RankedTier::MASTER->getScore() + $ranked->getLeaguePoints();
        }

        return $ranked->getTier()->getScore()
            + $ranked->getDivision()->getScore()
            + $ranked->getLeaguePoints();
    }
}
