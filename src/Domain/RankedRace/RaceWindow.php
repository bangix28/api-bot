<?php

namespace App\Domain\RankedRace;

/**
 * Fenêtre de course, en intervalle DEMI-OUVERT : [startsAt, endsAt[.
 *
 * Les bornes étaient incluses tant que les snapshots étaient quotidiens. Avec des
 * snapshots horodatés, une borne de fin incluse au jour près exclurait tout le
 * dernier jour à partir de 00:00:01. La borne de fin est donc l'instant qui suit
 * immédiatement la fenêtre — minuit du lendemain pour une fenêtre calendaire.
 *
 * Le contrat JSON, lui, reste exprimé en dernier jour INCLUS (cf. endDate()).
 */
final readonly class RaceWindow
{
    /**
     * Ancienneté maximale du report : le dernier relevé connu juste AVANT la
     * fenêtre, qui affine le rang de départ d'un joueur ayant joué très tôt
     * dans la période.
     *
     * Calée sur la cadence de collecte (30 minutes) et non sur des jours. Le
     * battement quotidien garantit déjà une ligne À L'INTÉRIEUR de chaque
     * fenêtre : le report n'a donc pas à faire apparaître un joueur, seulement
     * à capter le relevé immédiatement antérieur.
     *
     * Une borne large serait activement nuisible : le segment reliant un relevé
     * de la veille au premier relevé de la fenêtre crediterait à celle-ci
     * toutes les parties jouées entre les deux, c'est-à-dire avant son début.
     */
    private const string CARRY_IN_MAX_AGE = '-2 hours';

    private function __construct(
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
    ) {
    }

    /**
     * Depuis deux dates civiles à bornes incluses : périodes calendaires et
     * événements admin, dont les dates sont saisies au jour.
     *
     * setTime() n'est pas cosmétique : « monday this week » remet bien l'heure à
     * zéro, mais « first day of this month » CONSERVE l'heure courante.
     */
    public static function fromDays(\DateTimeImmutable $firstDay, \DateTimeImmutable $lastDay): self
    {
        return new self(
            $firstDay->setTime(0, 0),
            $lastDay->setTime(0, 0)->modify('+1 day'),
        );
    }

    /** Depuis deux instants, la fin étant exclue. */
    public static function between(\DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): self
    {
        return new self($startsAt, $endsAt);
    }

    public function contains(\DateTimeImmutable $at): bool
    {
        return $at >= $this->startsAt && $at < $this->endsAt;
    }

    /** Borne basse de recherche du report : [carryInStart, startsAt[. */
    public function carryInStart(): \DateTimeImmutable
    {
        return $this->startsAt->modify(self::CARRY_IN_MAX_AGE);
    }

    /** Le relevé est-il un report exploitable pour cette fenêtre ? */
    public function isCarryIn(\DateTimeImmutable $at): bool
    {
        return $at < $this->startsAt && $at >= $this->carryInStart();
    }

    public function startDate(): string
    {
        return $this->startsAt->format('Y-m-d');
    }

    /** Dernier jour INCLUS : la borne de fin est exclue, on recule d'une seconde. */
    public function endDate(): string
    {
        return $this->endsAt->modify('-1 second')->format('Y-m-d');
    }
}
