<?php

namespace App\Domain\LanChallenge;

/**
 * Lecture du fil d'exploits. Port de requête et non méthode du dépôt : l'heure
 * de règlement d'une manche est une donnée d'affichage, que le modèle des
 * parcours n'a pas besoin de porter.
 */
interface LanFeedQueryInterface
{
    /** @return FeedEntry[] manches réglées de la LAN, la plus récente en premier */
    public function latest(int $lanId, int $limit): array;
}
