<?php

namespace App\Domain\LanChallenge;

/**
 * Catalogue des cartes défis.
 *
 * Une carte n'y figure que si sa condition est mesurable par la Live Client
 * Data API (ADR-0005) : multi-kills, morts, KDA, participation, solo kills,
 * Aces, et — si la source les fournit — victoire et premier sang. Les cartes
 * de dégâts, soins ou CC attendront une source fiable.
 *
 * Les seuils sont des premières estimations pour l'ARAM du chaos, à caler sur
 * de vraies parties.
 */
enum Challenge: string
{
    // Argent
    case DOUBLE_KILL = 'double_kill';
    case PRUDENT = 'prudent';
    case TEAM_SPIRIT = 'team_spirit';
    case HUNTER = 'hunter';
    case WINGMAN = 'wingman';

    // Or
    case TRIPLE_KILL = 'triple_kill';
    case EXECUTIONER = 'executioner';
    case DUELIST = 'duelist';
    case CLEANER = 'cleaner';
    case FIRST_BLOOD = 'first_blood';
    case UNTOUCHABLE = 'untouchable';

    // Prismatique
    case QUADRA_KILL = 'quadra_kill';
    case PENTA_KILL = 'penta_kill';
    case IMMORTAL = 'immortal';
    case BLITZ = 'blitz';

    private const int BLITZ_MAX_SECONDS = 15 * 60;

    public function label(): string
    {
        return match ($this) {
            self::DOUBLE_KILL => 'Doublé',
            self::PRUDENT => 'Prudent',
            self::TEAM_SPIRIT => 'Esprit d\'équipe',
            self::HUNTER => 'Chasseur',
            self::WINGMAN => 'Bras droit',
            self::TRIPLE_KILL => 'Triplé',
            self::EXECUTIONER => 'Exécuteur',
            self::DUELIST => 'Duelliste',
            self::CLEANER => 'Nettoyeur',
            self::FIRST_BLOOD => 'Premier sang',
            self::UNTOUCHABLE => 'Intouchable',
            self::QUADRA_KILL => 'Quadra',
            self::PENTA_KILL => 'Penta',
            self::IMMORTAL => 'Immortel',
            self::BLITZ => 'Blitz',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DOUBLE_KILL => 'Réalise au moins un doublé.',
            self::PRUDENT => 'Termine la partie avec 5 morts au maximum.',
            self::TEAM_SPIRIT => 'Participe à au moins 60 % des kills de ton équipe.',
            self::HUNTER => 'Réalise au moins 10 kills.',
            self::WINGMAN => 'Réalise au moins 20 assistances.',
            self::TRIPLE_KILL => 'Réalise au moins un triplé.',
            self::EXECUTIONER => 'Termine avec un KDA d\'au moins 5.',
            self::DUELIST => 'Réalise au moins 2 kills sans aucune aide.',
            self::CLEANER => 'Porte le dernier coup d\'un Ace.',
            self::FIRST_BLOOD => 'Réalise le premier sang de la partie.',
            self::UNTOUCHABLE => 'Gagne avec 3 morts au maximum.',
            self::QUADRA_KILL => 'Réalise au moins un quadra.',
            self::PENTA_KILL => 'Réalise un penta.',
            self::IMMORTAL => 'Gagne sans mourir.',
            self::BLITZ => 'Gagne une partie de moins de 15 minutes.',
        };
    }

    public function rarity(): Rarity
    {
        return match ($this) {
            self::DOUBLE_KILL, self::PRUDENT, self::TEAM_SPIRIT, self::HUNTER, self::WINGMAN => Rarity::SILVER,
            self::TRIPLE_KILL, self::EXECUTIONER, self::DUELIST, self::CLEANER, self::FIRST_BLOOD, self::UNTOUCHABLE => Rarity::GOLD,
            self::QUADRA_KILL, self::PENTA_KILL, self::IMMORTAL, self::BLITZ => Rarity::PRISMATIC,
        };
    }

    public function points(): int
    {
        // Le penta est la seule carte au-dessus du barème de sa rareté :
        // à 60 points, elle vaudrait autant qu'un Blitz bien plus accessible.
        return $this === self::PENTA_KILL ? 100 : $this->rarity()->points();
    }

    public function evaluate(PlayerGameStats $stats, GameReport $game): Verdict
    {
        return match ($this) {
            self::DOUBLE_KILL => Verdict::of($stats->largestMultiKill >= 2),
            self::PRUDENT => Verdict::of($stats->deaths <= 5),
            self::TEAM_SPIRIT => Verdict::of($stats->killParticipation() >= 0.6),
            self::HUNTER => Verdict::of($stats->kills >= 10),
            self::WINGMAN => Verdict::of($stats->assists >= 20),
            self::TRIPLE_KILL => Verdict::of($stats->largestMultiKill >= 3),
            self::EXECUTIONER => Verdict::of($stats->kda() >= 5.0),
            self::DUELIST => Verdict::of($stats->soloKills >= 2),
            self::CLEANER => Verdict::of($stats->aces >= 1),
            self::FIRST_BLOOD => $stats->firstBlood === null ? Verdict::UNKNOWN : Verdict::of($stats->firstBlood),
            self::UNTOUCHABLE => self::ifWon($stats, $stats->deaths <= 3),
            self::QUADRA_KILL => Verdict::of($stats->largestMultiKill >= 4),
            self::PENTA_KILL => Verdict::of($stats->largestMultiKill >= 5),
            self::IMMORTAL => self::ifWon($stats, $stats->deaths === 0),
            self::BLITZ => self::ifWon($stats, $game->durationSeconds < self::BLITZ_MAX_SECONDS),
        };
    }

    /** @return self[] */
    public static function ofRarity(Rarity $rarity): array
    {
        return array_values(array_filter(self::cases(), static fn(self $c) => $c->rarity() === $rarity));
    }

    /** Une carte « gagne… » ne se juge que si la source connaît le résultat. */
    private static function ifWon(PlayerGameStats $stats, bool $condition): Verdict
    {
        if ($stats->win === null) {
            return Verdict::UNKNOWN;
        }

        return Verdict::of($stats->win && $condition);
    }
}
