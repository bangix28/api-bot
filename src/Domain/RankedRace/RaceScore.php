<?php

namespace App\Domain\RankedRace;

use App\Domain\RiotAccount\RankedQueueEntity;

/**
 * Position d'un rang sur l'échelle de la course, comptée en LP cumulés depuis
 * Iron IV 0 LP. Distincte de RankedQueueEntity::getScore(), qui sert le
 * classement « rang absolu » et reste persistée en base.
 *
 * L'échelle est CONTINUE : un point vaut un LP partout, y compris au
 * franchissement d'un palier. Un palier occupe exactement les 400 LP de ses
 * quatre divisions, si bien qu'Emerald I 99 LP et Diamond IV 0 LP sont voisins.
 * L'échelle précédente espaçait les paliers de 1000 pour 400 LP d'amplitude :
 * chaque promotion fabriquait 600 points qu'aucune partie n'avait gagnés, et un
 * joueur promu en Diamond affichait 1 448,8 points de progression pour 276 LP
 * réellement gagnés (constaté en prod le 2026-08-20).
 *
 * Le plancher apex prolonge Diamond I 99 LP sans discontinuité, et le libellé
 * GM/Challenger reste cosmétique : le compter à part fabriquerait de nouveaux
 * deltas fantômes au moment de la promotion.
 */
final class RaceScore
{
    private const int LP_PER_DIVISION = 100;

    /** Quatre divisions de 100 LP : la largeur d'un palier sur l'échelle. */
    public const int LP_PER_TIER = 400;

    public static function of(RankedQueueEntity $ranked): int
    {
        $tier = $ranked->getTier();
        $floor = $tier->index() * self::LP_PER_TIER;

        if ($tier->isApex()) {
            return $floor + $ranked->getLeaguePoints();
        }

        return $floor
            + $ranked->getDivision()->index() * self::LP_PER_DIVISION
            + $ranked->getLeaguePoints();
    }
}
