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
 * Les seuils sont des premières estimations, à caler sur de vraies parties.
 * Certaines cartes en ont un pour l'ARAM du chaos et un autre pour la Faille de
 * l'invocateur (classées) : les parties y sont plus longues, moins sanglantes,
 * et l'abandon n'est possible qu'à partir de 15 minutes.
 *
 * Saboteur : cartes secrètes. Celles qui sabotent la partie se jouent comme les
 * autres (jugées en fin de match). Celles qui visent un joueur ne se jouent pas
 * sur un match : leur effet est immédiat (cf. SabotageService).
 */
enum Challenge: string
{
    /** Tout pour toi : laisser les kills aux autres, quelle que soit la file. */
    private const int ALL_FOR_YOU_MAX_KILLS = 3;

    // Argent
    case DOUBLE_KILL = 'double_kill';
    case PRUDENT = 'prudent';
    case TEAM_SPIRIT = 'team_spirit';
    case HUNTER = 'hunter';
    case WINGMAN = 'wingman';
    case WHERE_IS_MAMA = 'where_is_mama';

    // Or
    case TRIPLE_KILL = 'triple_kill';
    case EXECUTIONER = 'executioner';
    case DUELIST = 'duelist';
    case CLEANER = 'cleaner';
    case FIRST_BLOOD = 'first_blood';
    case UNTOUCHABLE = 'untouchable';
    case ALL_FOR_YOU = 'all_for_you';
    case AVENGER = 'avenger';

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

    public function label(): string
    {
        return match ($this) {
            self::DOUBLE_KILL => 'Doublé',
            self::PRUDENT => 'Prudent',
            self::TEAM_SPIRIT => 'Esprit d\'équipe',
            self::HUNTER => 'Chasseur',
            self::WINGMAN => 'Bras droit',
            self::WHERE_IS_MAMA => 'Où est maman ?',
            self::TRIPLE_KILL => 'Triplé',
            self::EXECUTIONER => 'Exécuteur',
            self::DUELIST => 'Duelliste',
            self::CLEANER => 'Nettoyeur',
            self::FIRST_BLOOD => 'Premier sang',
            self::UNTOUCHABLE => 'Intouchable',
            self::ALL_FOR_YOU => 'Tout pour toi',
            self::AVENGER => 'Ange vengeur',
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
            self::PRUDENT => $this->describe('Termine la partie avec %d morts au maximum.'),
            self::TEAM_SPIRIT => $this->describe('Participe à au moins %d %% des kills de ton équipe.', ' %'),
            self::HUNTER => $this->describe('Réalise au moins %d kills.'),
            self::WINGMAN => $this->describe('Réalise au moins %d assistances.'),
            self::WHERE_IS_MAMA => $this->describe('Fais une assistance sur au moins %d kills d\'un même coéquipier.'),
            self::TRIPLE_KILL => 'Réalise au moins un triplé.',
            self::EXECUTIONER => 'Termine avec un KDA d\'au moins 5.',
            self::DUELIST => 'Réalise au moins 2 kills sans aucune aide.',
            self::CLEANER => 'Porte le dernier coup d\'un Ace.',
            self::FIRST_BLOOD => 'Réalise le premier sang de la partie.',
            self::UNTOUCHABLE => $this->describe('Gagne avec %d morts au maximum.'),
            self::ALL_FOR_YOU => $this->describe(
                sprintf('Réalise au moins %%d assistances avec %d kills au maximum.', self::ALL_FOR_YOU_MAX_KILLS),
                ' assistances',
            ),
            self::AVENGER => $this->describe('Venge un coéquipier au moins %d fois : tue son tueur dans les 10 secondes.'),
            self::QUADRA_KILL => 'Réalise au moins un quadra.',
            self::PENTA_KILL => 'Réalise un penta.',
            self::IMMORTAL => 'Gagne sans mourir.',
            self::BLITZ => $this->describe('Gagne une partie de moins de %d minutes.', ' minutes'),
            self::BAD_LOSER => 'Perds la partie.',
            self::MARTYR => 'Termine avec le plus de morts de la partie.',
            self::PICKPOCKET => 'Vole une relance au joueur de ton choix.',
            self::CURSE => 'Le prochain défi réussi du joueur de ton choix ne lui rapporte que la moitié des points. Il ne le saura qu\'au règlement.',
        };
    }

    public function rarity(): Rarity
    {
        return match ($this) {
            self::DOUBLE_KILL, self::PRUDENT, self::TEAM_SPIRIT, self::HUNTER, self::WINGMAN, self::WHERE_IS_MAMA => Rarity::SILVER,
            self::TRIPLE_KILL, self::EXECUTIONER, self::DUELIST, self::CLEANER, self::FIRST_BLOOD, self::UNTOUCHABLE,
            self::ALL_FOR_YOU, self::AVENGER => Rarity::GOLD,
            self::QUADRA_KILL, self::PENTA_KILL, self::IMMORTAL, self::BLITZ => Rarity::PRISMATIC,
            self::BAD_LOSER, self::MARTYR, self::PICKPOCKET, self::CURSE => Rarity::SABOTEUR,
        };
    }

    public function points(): int
    {
        return match ($this) {
            // L'Immortel est la seule carte au-dessus du barème de sa rareté.
            self::IMMORTAL => 100,
            // Saboter un joueur ne rapporte rien : l'effet est la récompense.
            self::PICKPOCKET, self::CURSE => 0,
            default => $this->rarity()->points(),
        };
    }

    public function theme(): ?Theme
    {
        return match ($this) {
            self::WHERE_IS_MAMA, self::ALL_FOR_YOU, self::AVENGER => Theme::BOND,
            default => null,
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
            self::PRUDENT => Verdict::of($stats->deaths <= $this->threshold($game->queue)),
            self::TEAM_SPIRIT => Verdict::of($stats->killParticipation() >= $this->threshold($game->queue) / 100),
            self::HUNTER => Verdict::of($stats->kills >= $this->threshold($game->queue)),
            self::WINGMAN => Verdict::of($stats->assists >= $this->threshold($game->queue)),
            self::WHERE_IS_MAMA => Verdict::of($stats->mostAssistsOnOneAlly >= $this->threshold($game->queue)),
            self::TRIPLE_KILL => Verdict::of($stats->largestMultiKill >= 3),
            self::EXECUTIONER => Verdict::of($stats->kda() >= 5.0),
            self::DUELIST => Verdict::of($stats->soloKills >= 2),
            self::CLEANER => Verdict::of($stats->aces >= 1),
            self::FIRST_BLOOD => $stats->firstBlood === null ? Verdict::UNKNOWN : Verdict::of($stats->firstBlood),
            self::UNTOUCHABLE => self::ifWon($stats, $stats->deaths <= $this->threshold($game->queue)),
            self::ALL_FOR_YOU => Verdict::of($stats->assists >= $this->threshold($game->queue) && $stats->kills <= self::ALL_FOR_YOU_MAX_KILLS),
            self::AVENGER => Verdict::of($stats->avengedAllies >= $this->threshold($game->queue)),
            self::QUADRA_KILL => Verdict::of($stats->largestMultiKill >= 4),
            self::PENTA_KILL => Verdict::of($stats->largestMultiKill >= 5),
            self::IMMORTAL => self::ifWon($stats, $stats->deaths === 0),
            self::BLITZ => self::ifWon($stats, $game->durationSeconds < $this->threshold($game->queue) * 60),
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

    /**
     * Seuils des cartes qui en changent selon la carte : [ARAM du chaos, Faille].
     * Pourcentage pour Esprit d'équipe, minutes pour Blitz.
     *
     * @return array{int, int}|null
     */
    private function thresholds(): ?array
    {
        return match ($this) {
            self::PRUDENT => [5, 3],
            self::TEAM_SPIRIT => [60, 55],
            self::HUNTER => [10, 7],
            self::WINGMAN => [20, 12],
            // En ARAM du chaos, un joueur meurt environ 8 fois par partie.
            self::UNTOUCHABLE => [5, 2],
            self::WHERE_IS_MAMA => [12, 6],
            self::ALL_FOR_YOU => [20, 12],
            self::AVENGER => [3, 2],
            // Sur la Faille, une victoire avant 15 minutes est quasi impossible (abandon à 15 min).
            self::BLITZ => [15, 22],
            default => null,
        };
    }

    private function threshold(LanQueue $queue): int
    {
        [$aramThreshold, $riftThreshold] = $this->thresholds()
            ?? throw new \LogicException(sprintf('La carte %s n\'a pas de seuil.', $this->value));

        return $queue->playsOnRift() ? $riftThreshold : $aramThreshold;
    }

    /**
     * « Réalise au moins 10 kills (7 sur la Faille). » : la carte est choisie
     * avant la partie, sans en connaître la file.
     */
    private function describe(string $sentence, string $riftUnit = ''): string
    {
        [$aramThreshold, $riftThreshold] = $this->thresholds()
            ?? throw new \LogicException(sprintf('La carte %s n\'a pas de seuil.', $this->value));

        return sprintf('%s (%d%s sur la Faille).', rtrim(sprintf($sentence, $aramThreshold), '.'), $riftThreshold, $riftUnit);
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
