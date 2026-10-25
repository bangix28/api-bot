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
 */
final readonly class PlayerRun
{
    public const int INITIAL_REROLLS = 3;

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
    ) {
    }

    public static function start(int $lanId, string $puuid, CardOffer $firstOffer): self
    {
        return new self($lanId, $puuid, self::INITIAL_REROLLS, 0, $firstOffer, null, null, []);
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
    ): self {
        return new self($lanId, $puuid, $rerollsLeft, $score, $offer, $activeCard, $lockedGameId, $results);
    }

    public function reroll(int $slot, CardDealer $dealer): self
    {
        $offer = $this->requireOffer();

        if ($this->rerollsLeft === 0) {
            throw new NoRerollLeftException('Plus aucune relance disponible.');
        }

        return new self(
            $this->lanId, $this->puuid, $this->rerollsLeft - 1, $this->score,
            $dealer->reroll($offer, $slot), $this->activeCard, $this->lockedGameId, $this->results,
        );
    }

    public function pick(int $slot): self
    {
        $card = $this->requireOffer()->cardAt($slot);

        return new self(
            $this->lanId, $this->puuid, $this->rerollsLeft, $this->score,
            null, $card, $this->lockedGameId, $this->results,
        );
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

        return new self(
            $this->lanId, $this->puuid, $this->rerollsLeft, $this->score,
            $this->offer, $this->activeCard, $gameId, $this->results,
        );
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
     * Clôt la manche : points, relances rendues, et offre suivante.
     *
     * - Réussite : points de la carte, majorés du bonus de manche le cas échéant.
     * - Échec d'une carte prismatique : une relance rendue, pour que viser haut
     *   ne soit pas puni deux fois.
     * - Verdict inconnu : aucun point, mais une relance rendue en dédommagement
     *   d'une carte perdue faute de donnée.
     */
    public function settle(string $gameId, Verdict $verdict, bool $roundBonus, CardOffer $nextOffer): self
    {
        $card = $this->requireLockedOn($gameId);

        $points = 0;
        if ($verdict === Verdict::SUCCESS) {
            $points = $roundBonus
                ? intdiv($card->points() * (100 + RoundResolver::ROUND_BONUS_PERCENT), 100)
                : $card->points();
        }

        $refund = match (true) {
            $verdict === Verdict::UNKNOWN => 1,
            $verdict === Verdict::FAILURE && $card->rarity() === Rarity::PRISMATIC => 1,
            default => 0,
        };

        return new self(
            $this->lanId, $this->puuid, $this->rerollsLeft + $refund, $this->score + $points,
            $nextOffer, null, null,
            [...$this->results, new RoundResult($gameId, $card, $verdict, $points, $roundBonus && $verdict === Verdict::SUCCESS)],
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
