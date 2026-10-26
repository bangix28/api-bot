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
 *
 * Saboteur : cartes secrètes. Celles qui sabotent la partie se jouent comme les
 * autres (jugées en fin de match). Celles qui visent un joueur ne se jouent pas
 * sur un match : leur effet est immédiat (cf. SabotageService).
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

    // Saboteur — contre la partie
    case BAD_LOSER = 'bad_loser';
    // « Gameplay Shaco » pour les joueurs ; le code « martyr » reste stable pour le front.
    case MARTYR = 'martyr';
    // Saboteur — contre un joueur
    case PICKPOCKET = 'pickpocket';
    case CURSE = 'curse';

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
            self::BAD_LOSER => 'Mauvais perdant',
            self::MARTYR => 'Gameplay Shaco',
            self::PICKPOCKET => 'Pickpocket',
            self::CURSE => 'Malédiction',
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
            self::BAD_LOSER => 'Perds la partie.',
            self::MARTYR => 'Termine avec le plus de morts de la partie.',
            self::PICKPOCKET => 'Vole une relance au joueur de ton choix.',
            self::CURSE => 'Le prochain défi réussi du joueur de ton choix ne lui rapporte que la moitié des points. Il ne le saura qu\'au règlement.',
        };
    }

    public function rarity(): Rarity
    {
        return match ($this) {
            self::DOUBLE_KILL, self::PRUDENT, self::TEAM_SPIRIT, self::HUNTER, self::WINGMAN => Rarity::SILVER,
            self::TRIPLE_KILL, self::EXECUTIONER, self::DUELIST, self::CLEANER, self::FIRST_BLOOD, self::UNTOUCHABLE => Rarity::GOLD,
            self::QUADRA_KILL, self::PENTA_KILL, self::IMMORTAL, self::BLITZ => Rarity::PRISMATIC,
            self::BAD_LOSER, self::MARTYR, self::PICKPOCKET, self::CURSE => Rarity::SABOTEUR,
        };
    }

    public function points(): int
    {
        return match ($this) {
            // Le penta est la seule carte au-dessus du barème de sa rareté :
            // à 60 points, elle vaudrait autant qu'un Blitz bien plus accessible.
            self::PENTA_KILL => 100,
            // Saboter un joueur ne rapporte rien : l'effet est la récompense.
            self::PICKPOCKET, self::CURSE => 0,
            default => $this->rarity()->points(),
        };
    }

    /** Carte qui vise un autre joueur : effet immédiat, jamais jouée sur un match. */
    public function targetsPlayer(): bool
    {
        return $this === self::PICKPOCKET || $this === self::CURSE;
    }

    /** Carte cachée aux autres joueurs tant qu'elle n'est pas réglée. */
    public function isSecret(): bool
    {
        return $this->rarity() === Rarity::SABOTEUR;
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
            self::BAD_LOSER => $stats->win === null ? Verdict::UNKNOWN : Verdict::of(!$stats->win),
            self::MARTYR => $stats->mostDeathsInGame === null ? Verdict::UNKNOWN : Verdict::of($stats->mostDeathsInGame),
            self::PICKPOCKET, self::CURSE => throw new \LogicException(sprintf(
                'La carte %s vise un joueur : elle ne se joue pas sur un match.',
                $this->value,
            )),
        };
    }

    /** @return self[] */
    public static function ofRarity(Rarity $rarity): array
    {
        return array_values(array_filter(self::cases(), static fn(self $card) => $card->rarity() === $rarity));
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
