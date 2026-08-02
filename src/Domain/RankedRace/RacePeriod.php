<?php

namespace App\Domain\RankedRace;

enum RacePeriod: string
{
    case WEEK = 'week';
    case MONTH = 'month';

    public static function fromQueryParam(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new InvalidRankedRaceParameterException(
                sprintf('Période invalide : "%s" (attendu : week ou month)', $value)
            );
    }

    /** Fenêtre calendaire courante : semaine ISO (lundi→dimanche) ou mois civil. */
    public function windowFor(\DateTimeImmutable $today): RaceWindow
    {
        return match ($this) {
            self::WEEK => new RaceWindow(
                $today->modify('monday this week'),
                $today->modify('sunday this week'),
            ),
            self::MONTH => new RaceWindow(
                $today->modify('first day of this month'),
                $today->modify('last day of this month'),
            ),
        };
    }

    /** Seuil de qualification du classement Winrate. */
    public function minGamesToQualify(): int
    {
        return match ($this) {
            self::WEEK => 5,
            self::MONTH => 15,
        };
    }
}
