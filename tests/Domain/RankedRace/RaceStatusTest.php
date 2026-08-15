<?php

namespace App\Tests\Domain\RankedRace;

use App\Domain\RankedRace\RaceStatus;
use App\Domain\RankedRace\RaceWindow;
use PHPUnit\Framework\TestCase;

class RaceStatusTest extends TestCase
{
    public function testAvantLOuvertureDeLaFenetre(): void
    {
        $this->assertSame(
            RaceStatus::UPCOMING,
            RaceStatus::of($this->semaine(), hasAnyGame: false, now: new \DateTimeImmutable('2026-08-02 23:59:59')),
        );
    }

    public function testFenetreOuverteMaisPersonneNAJoue(): void
    {
        // L'état qui donne tout son sens au « départ à la première game » :
        // la course existe mais n'a pas démarré. Le front n'affiche pas un
        // tableau vide, il affiche un appel à jouer.
        $this->assertSame(
            RaceStatus::AWAITING_FIRST_GAME,
            RaceStatus::of($this->semaine(), hasAnyGame: false, now: new \DateTimeImmutable('2026-08-05 12:00')),
        );
    }

    public function testFenetreOuverteEtAuMoinsUnePartieJouee(): void
    {
        $this->assertSame(
            RaceStatus::RUNNING,
            RaceStatus::of($this->semaine(), hasAnyGame: true, now: new \DateTimeImmutable('2026-08-05 12:00')),
        );
    }

    public function testJusteApresLaFermetureLeClassementNEstPasDefinitif(): void
    {
        // Une partie terminée à 23h58 n'est observée qu'au relevé suivant :
        // annoncer « terminé » tout de suite serait mentir.
        $this->assertSame(
            RaceStatus::SETTLING,
            RaceStatus::of($this->semaine(), hasAnyGame: true, now: new \DateTimeImmutable('2026-08-10 00:00:01')),
        );
        $this->assertSame(
            RaceStatus::SETTLING,
            RaceStatus::of($this->semaine(), hasAnyGame: true, now: new \DateTimeImmutable('2026-08-10 00:44:59')),
        );
    }

    public function testApresLaMargeDeClotureLeClassementEstDefinitif(): void
    {
        $this->assertSame(
            RaceStatus::FINISHED,
            RaceStatus::of($this->semaine(), hasAnyGame: true, now: new \DateTimeImmutable('2026-08-10 00:45:00')),
        );
    }

    public function testUneSemaineOuPersonneNAJoueSeTermineVide(): void
    {
        $status = RaceStatus::of($this->semaine(), hasAnyGame: false, now: new \DateTimeImmutable('2026-08-11 12:00'));

        $this->assertSame(RaceStatus::EMPTY_RACE, $status);
        $this->assertSame('empty', $status->value);
    }

    public function testLeDernierInstantDeLaFenetreEstEncoreEnCours(): void
    {
        $this->assertSame(
            RaceStatus::RUNNING,
            RaceStatus::of($this->semaine(), hasAnyGame: true, now: new \DateTimeImmutable('2026-08-09 23:59:59')),
        );
    }

    private function semaine(): RaceWindow
    {
        return RaceWindow::fromDays(new \DateTimeImmutable('2026-08-03'), new \DateTimeImmutable('2026-08-09'));
    }
}
