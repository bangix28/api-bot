<?php

namespace App\Domain\RankedRace;

/**
 * Cycle de vie d'une course, tel que le front doit l'afficher.
 *
 * Distinct de RaceEventStatus, qui ne connaît que le calendrier d'un événement :
 * celui-ci tient compte du fait qu'une partie ait été jouée ou non, et du délai
 * de clôture.
 */
enum RaceStatus: string
{
    /** Fenêtre pas encore ouverte (événements à dates libres uniquement). */
    case UPCOMING = 'upcoming';

    /** Ouverte, mais personne n'a encore joué : la course n'a pas démarré. */
    case AWAITING_FIRST_GAME = 'awaiting_first_game';

    case RUNNING = 'running';

    /**
     * Fenêtre fermée, mais le classement n'est pas définitif : une partie
     * terminée juste avant la clôture n'est observée qu'au relevé suivant.
     */
    case SETTLING = 'settling';

    case FINISHED = 'finished';

    /** Fenêtre close sans qu'une seule partie ait été jouée. */
    case EMPTY_RACE = 'empty';

    /**
     * Marge de clôture : le temps qu'il faut au cron pour observer les dernières
     * parties de la fenêtre. Une cadence de 30 minutes plus la durée d'un run.
     */
    private const string SETTLING_DELAY = '+45 minutes';

    public static function of(RaceWindow $window, bool $hasAnyGame, \DateTimeImmutable $now): self
    {
        if ($now < $window->startsAt) {
            return self::UPCOMING;
        }

        if ($window->contains($now)) {
            return $hasAnyGame ? self::RUNNING : self::AWAITING_FIRST_GAME;
        }

        if ($now < $window->endsAt->modify(self::SETTLING_DELAY)) {
            return self::SETTLING;
        }

        return $hasAnyGame ? self::FINISHED : self::EMPTY_RACE;
    }
}
