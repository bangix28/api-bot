<?php

namespace App\Domain\LanChallenge;

/**
 * Le parcours d'un joueur pendant une LAN : son offre en cours, la carte qu'il
 * a choisie, ses relances restantes, ses points et l'historique de ses manches.
 *
 * Immuable : chaque action renvoie un nouveau parcours.
 *
 * Cycle d'une manche : offre → choix (définitif) → verrouillage au lancement de
 * la partie → règlement à la fin, qui distribue l'offre suivante. Un joueur qui
 * lance une partie sans avoir choisi la joue sans carte et garde son offre.
 *
 * Une carte qui vise un joueur (Pickpocket, Malédiction) ne se choisit pas : son
 * effet est immédiat (SabotageService), et le saboteur reçoit aussitôt une
 * nouvelle offre pour jouer son match comme les autres.
 */
final readonly class PlayerRun
{
    public const int INITIAL_REROLLS = 3;
    public const int ROUND_REROLLS_CAP = 3;
    public const int CURSE_PERCENT = 50;

    /**
     * @param RoundResult[] $results
     */
    private function __construct(
        public int $lanId,
        public string $puuid,
        public int $rerollsLeft,
        public int $score,
        public ?CardOffer $offer,
        public ?Challenge $activeCard,
        public ?string $lockedGameId,
        public array $results,
        /**
         * Maudit : le prochain défi réussi ne rapporte que CURSE_PERCENT % des
         * points. Le joueur ne doit pas le savoir avant ce règlement.
         */
        public bool $cursed,
    ) {
    }

    public static function start(int $lanId, string $puuid, CardOffer $firstOffer): self
    {
        return new self($lanId, $puuid, self::INITIAL_REROLLS, 0, $firstOffer, null, null, [], false);
    }

    /**
     * Reconstitue un parcours persisté : aucune règle de cycle n'est rejouée.
     *
     * @param RoundResult[] $results
     */
    public static function restore(
        int $lanId,
        string $puuid,
        int $rerollsLeft,
        int $score,
        ?CardOffer $offer,
        ?Challenge $activeCard,
        ?string $lockedGameId,
        array $results,
        bool $cursed = false,
    ): self {
        return new self($lanId, $puuid, $rerollsLeft, $score, $offer, $activeCard, $lockedGameId, $results, $cursed);
    }

    public function reroll(int $slot, CardDealer $dealer): self
    {
        $offer = $this->requireOffer();

        if ($this->rerollsLeft === 0) {
            throw new NoRerollLeftException('Plus aucune relance disponible.');
        }

        return $this->with(rerollsLeft: $this->rerollsLeft - 1, offer: $dealer->reroll($offer, $slot));
    }

    /** Carte de l'offre en cours à cet emplacement. */
    public function cardInOffer(int $slot): Challenge
    {
        return $this->requireOffer()->cardAt($slot);
    }

    public function pick(int $slot): self
    {
        $card = $this->cardInOffer($slot);

        if ($card->targetsPlayer()) {
            throw new SabotageTargetRequiredException(sprintf('La carte « %s » vise un joueur : choisis ta cible.', $card->label()));
        }

        return $this->with(offer: null, activeCard: $card);
    }

    /**
     * Le saboteur a joué une carte qui vise un joueur : elle quitte son offre, il
     * reçoit une offre neuve (et les relances gagnées) pour jouer son match.
     */
    public function afterSabotage(int $slot, CardOffer $nextOffer, int $rerollsGained): self
    {
        if (!$this->cardInOffer($slot)->targetsPlayer()) {
            throw new \LogicException('Seule une carte qui vise un joueur se joue en sabotage.');
        }

        return $this->with(rerollsLeft: $this->rerollsLeft + $rerollsGained, offer: $nextOffer);
    }

    /** Pickpocket : la cible perd une relance. */
    public function loseReroll(): self
    {
        if ($this->rerollsLeft === 0) {
            throw new TargetHasNoRerollException('Ce joueur n\'a plus de relance à voler.');
        }

        return $this->with(rerollsLeft: $this->rerollsLeft - 1);
    }

    /**
     * Malédiction. Ne se cumule pas, et sans le dire : refuser une seconde
     * malédiction révélerait au saboteur que la cible est déjà maudite.
     */
    public function curse(): self
    {
        return $this->with(cursed: true);
    }

    /**
     * La partie vient de démarrer : la carte choisie ne peut plus changer et ne
     * sera jugée que sur cette partie. Sans carte choisie, rien n'est verrouillé.
     */
    public function lockFor(string $gameId): self
    {
        if ($this->activeCard === null || $this->lockedGameId === $gameId) {
            return $this;
        }

        if ($this->lockedGameId !== null) {
            throw new CardAlreadyLockedException(sprintf(
                'La carte est déjà engagée sur la partie %s, toujours en attente de règlement.',
                $this->lockedGameId,
            ));
        }

        return $this->with(lockedGameId: $gameId);
    }

    public function isLockedOn(string $gameId): bool
    {
        return $this->lockedGameId === $gameId;
    }

    /** Verdict de la carte engagée sur cette partie. */
    public function evaluate(GameReport $report): Verdict
    {
        $card = $this->requireLockedOn($report->gameId);
        $stats = $report->statsFor($this->puuid);

        // Joueur absent du relevé : on ne sait pas, on ne sanctionne pas.
        return $stats === null ? Verdict::UNKNOWN : $card->evaluate($stats, $report);
    }

    /**
     * Clôt la manche : points, relances rechargées, et offre suivante.
     *
     * - Réussite : points de la carte, majorés du bonus de manche le cas échéant,
     *   puis réduits de moitié si le joueur est maudit (la malédiction est alors
     *   levée et révélée par le résultat).
     * - Quel que soit le verdict, les relances remontent à ROUND_REROLLS_CAP. Un
     *   stock déjà au-dessus (relances volées par Pickpocket) n'est pas rogné :
     *   le plafond borne le gain de la manche, pas ce que le joueur a gagné ailleurs.
     *
     * Une malédiction attend le prochain défi réussi : un échec ne la lève pas.
     */
    public function settle(string $gameId, Verdict $verdict, bool $roundBonus, CardOffer $nextOffer): self
    {
        $card = $this->requireLockedOn($gameId);
        $succeeded = $verdict === Verdict::SUCCESS;

        $points = 0;
        if ($succeeded) {
            $points = $roundBonus
                ? intdiv($card->points() * (100 + RoundResolver::ROUND_BONUS_PERCENT), 100)
                : $card->points();
        }

        $curseApplied = $succeeded && $this->cursed;
        if ($curseApplied) {
            $points = intdiv($points * self::CURSE_PERCENT, 100);
        }

        return $this->with(
            rerollsLeft: max($this->rerollsLeft, self::ROUND_REROLLS_CAP),
            score: $this->score + $points,
            offer: $nextOffer,
            activeCard: null,
            lockedGameId: null,
            results: [...$this->results, new RoundResult($gameId, $card, $verdict, $points, $roundBonus && $succeeded, $curseApplied)],
            cursed: $this->cursed && !$curseApplied,
        );
    }

    /**
     * Copie modifiée. Les champs nullables ne se « remettent à null » que s'ils
     * sont passés explicitement : on distingue « inchangé » de « vidé ».
     *
     * @param RoundResult[]|null $results
     */
    private function with(
        ?int $rerollsLeft = null,
        ?int $score = null,
        CardOffer|false|null $offer = false,
        Challenge|false|null $activeCard = false,
        string|false|null $lockedGameId = false,
        ?array $results = null,
        ?bool $cursed = null,
    ): self {
        return new self(
            $this->lanId,
            $this->puuid,
            $rerollsLeft ?? $this->rerollsLeft,
            $score ?? $this->score,
            $offer === false ? $this->offer : $offer,
            $activeCard === false ? $this->activeCard : $activeCard,
            $lockedGameId === false ? $this->lockedGameId : $lockedGameId,
            $results ?? $this->results,
            $cursed ?? $this->cursed,
        );
    }

    private function requireOffer(): CardOffer
    {
        return $this->offer ?? throw new NoOfferToChooseFromException('Aucune offre en cours : la carte est déjà choisie.');
    }

    private function requireLockedOn(string $gameId): Challenge
    {
        if ($this->activeCard === null || $this->lockedGameId !== $gameId) {
            throw new \LogicException(sprintf('Aucune carte engagée sur la partie %s pour ce joueur.', $gameId));
        }

        return $this->activeCard;
    }
}
