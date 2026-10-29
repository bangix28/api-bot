<?php

namespace App\Domain\LanChallenge;

/**
 * Ce qu'a fait un joueur dans une partie, tel que le relevé le rapporte.
 * Indépendant de la source (Live Client Data API aujourd'hui, cf. ADR-0005) :
 * le moteur de cartes ne connaît que ces chiffres.
 *
 * Les champs nullables sont ceux qu'une source peut ne pas fournir :
 * null signifie « inconnu », jamais « non ».
 */
final readonly class PlayerGameStats
{
    public function __construct(
        public string $puuid,
        public int $kills,
        public int $deaths,
        public int $assists,
        /** Plus longue série de kills enchaînés : 2 = doublé … 5 = penta, 0 ou 1 sinon. */
        public int $largestMultiKill,
        /** Kills sans aucun assistant. */
        public int $soloKills,
        /** Kills de toute l'équipe du joueur, pour la participation aux kills. */
        public int $teamKills,
        /** Aces dont le joueur a porté le dernier coup. */
        public int $aces,
        public ?bool $win = null,
        public ?bool $firstBlood = null,
        /**
         * Le joueur finit avec le plus de morts des 10 joueurs (égalité comprise).
         * Calculé par le serveur sur l'ensemble de la partie, que le relevé
         * d'un seul joueur ne suffit pas à établir.
         */
        public ?bool $mostDeathsInGame = null,
        /** Le plus grand nombre de kills d'un même coéquipier sur lesquels le joueur a fait une assistance. */
        public int $mostAssistsOnOneAlly = 0,
        /** Kills du joueur sur l'ennemi qui venait de tuer l'un de ses coéquipiers. */
        public int $avengedAllies = 0,
        /** Plus longue série de kills sans mourir, sur toute la partie (au-delà du penta). */
        public int $longestKillStreak = 0,
        /** Le plus grand nombre de fois où le joueur a tué un même ennemi. */
        public int $mostKillsOnOneEnemy = 0,
        /** @var list<int> Nombre d'assistants de chaque kill du joueur. */
        public array $assistersPerKill = [],
        /** @var list<float> Secondes de jeu de chaque kill du joueur. */
        public array $killTimes = [],
        /**
         * Le joueur finit avec strictement plus de kills que tous les autres
         * participants de la LAN de la partie ; null s'il en est le seul.
         */
        public ?bool $mostKillsAmongLan = null,
    ) {
        if ($kills < 0 || $deaths < 0 || $assists < 0 || $soloKills < 0 || $teamKills < 0 || $aces < 0
            || $mostAssistsOnOneAlly < 0 || $avengedAllies < 0 || $longestKillStreak < 0 || $mostKillsOnOneEnemy < 0) {
            throw new \InvalidArgumentException('Un relevé de partie ne peut pas contenir de compteur négatif.');
        }

        if ($largestMultiKill < 0 || $largestMultiKill > 5) {
            throw new \InvalidArgumentException('Une série de kills va de 0 à 5.');
        }
    }

    /** (kills + assistances) / morts, une partie sans mort comptant comme une mort. */
    public function kda(): float
    {
        return ($this->kills + $this->assists) / max(1, $this->deaths);
    }

    /** Part des kills de l'équipe auxquels le joueur a participé, entre 0 et 1. */
    public function killParticipation(): float
    {
        if ($this->teamKills === 0) {
            return 0.0;
        }

        return min(1.0, ($this->kills + $this->assists) / $this->teamKills);
    }
}
