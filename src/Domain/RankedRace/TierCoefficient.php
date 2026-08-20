<?php

namespace App\Domain\RankedRace;

use App\Domain\RiotAccount\RankedTier;

/**
 * Tarif d'un palier, appliqué aux LP gagnés DANS ce palier :
 * WeightedProgressionScale s'en sert comme pente sur la bande correspondante,
 * et découpe donc un segment qui traverse une frontière.
 *
 * Le tarif compense une difficulté réelle, pas la difficulté supposée du palier.
 * Sous Master, le montant de LP par partie ne dépend pas du palier mais de
 * l'écart entre le MMR et le rang affiché : un joueur à son niveau d'équilibre
 * gagne autant en Bronze qu'en Diamond, et nette zéro sur la semaine. Ce qui
 * produit du LP, c'est d'être sous-classé — et le convertir est plus facile en
 * bas, où les gains gonflés et les winrates à 70 % sont accessibles.
 *
 * D'où un tarif plat jusqu'à Platine, puis une correction modeste et tardive
 * pour les frictions propres au haut de l'échelle : décompression des gains,
 * retour rapide à 50 % de winrate, decay apex qui taxe l'activité, files
 * longues aux heures creuses, et impossibilité d'être sous-classé au sommet.
 *
 * La grille précédente allait de 1.0 à 2.2 en démarrant dès Silver : un joueur
 * Diamond assidu y devenait structurellement imbattable par un Silver faisant
 * la performance objectivement plus impressionnante, ce qui figeait le podium
 * et tuait l'intérêt de la course.
 */
final class TierCoefficient
{
    public static function for(RankedTier $tier): float
    {
        return match ($tier) {
            RankedTier::IRON,
            RankedTier::BRONZE,
            RankedTier::SILVER,
            RankedTier::GOLD,
            RankedTier::PLATINUM => 1.0,
            RankedTier::EMERALD => 1.05,
            RankedTier::DIAMOND => 1.15,
            RankedTier::MASTER, RankedTier::GRANDMASTER, RankedTier::CHALLENGER => 1.35,
            // Un snapshot n'est jamais créé pour un compte non classé.
            RankedTier::UNRANKED => throw new \InvalidArgumentException(
                'Pas de coefficient pour UNRANKED : un snapshot non classé ne devrait pas exister'
            ),
        };
    }

    /**
     * Plage des coefficients existants. Un segment n'ayant qu'un seul tier de
     * départ, son rapport pondéré/brut ne peut pas en sortir : c'est l'invariant
     * qui aurait attrapé le +1 448,8 pour 276 LP.
     *
     * Dérivée de la table plutôt que recopiée, pour qu'une recalibration ne
     * laisse pas un garde-fou périmé derrière elle.
     */
    public static function lowest(): float
    {
        return min(self::all());
    }

    public static function highest(): float
    {
        return max(self::all());
    }

    /** @return float[] */
    private static function all(): array
    {
        $coefficients = [];

        foreach (RankedTier::cases() as $tier) {
            if ($tier !== RankedTier::UNRANKED) {
                $coefficients[] = self::for($tier);
            }
        }

        return $coefficients;
    }
}
