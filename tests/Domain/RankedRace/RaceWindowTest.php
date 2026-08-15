<?php

namespace App\Tests\Domain\RankedRace;

use App\Domain\RankedRace\RaceWindow;
use PHPUnit\Framework\TestCase;

class RaceWindowTest extends TestCase
{
    public function testFromDaysNormaliseLHeureAMinuit(): void
    {
        // Pièce maîtresse du passage à l'horodaté : « first day of this month »
        // conserve l'heure courante, contrairement à « monday this week ».
        $window = RaceWindow::fromDays(
            new \DateTimeImmutable('2026-08-01 14:30:00'),
            new \DateTimeImmutable('2026-08-31 22:15:00'),
        );

        $this->assertSame('2026-08-01 00:00:00', $window->startsAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-01 00:00:00', $window->endsAt->format('Y-m-d H:i:s'));
    }

    public function testLaBorneDeFinEstLInstantQuiSuitLeDernierJour(): void
    {
        $window = RaceWindow::fromDays(
            new \DateTimeImmutable('2026-08-03'),
            new \DateTimeImmutable('2026-08-09'),
        );

        $this->assertSame('2026-08-10 00:00:00', $window->endsAt->format('Y-m-d H:i:s'));
    }

    public function testContainsInclutLeDebutEtExclutLaFin(): void
    {
        $window = RaceWindow::fromDays(
            new \DateTimeImmutable('2026-08-03'),
            new \DateTimeImmutable('2026-08-09'),
        );

        // Lundi minuit pile : dans la fenêtre.
        $this->assertTrue($window->contains(new \DateTimeImmutable('2026-08-03 00:00:00')));
        // Le cas que des bornes incluses au jour près faisaient tomber : la toute
        // fin du dernier jour appartient bien à la semaine.
        $this->assertTrue($window->contains(new \DateTimeImmutable('2026-08-09 23:59:59')));
        // Lundi suivant minuit pile : hors de la fenêtre, sans chevauchement.
        $this->assertFalse($window->contains(new \DateTimeImmutable('2026-08-10 00:00:00')));
        // La seconde qui précède le début.
        $this->assertFalse($window->contains(new \DateTimeImmutable('2026-08-02 23:59:59')));
    }

    public function testDeuxFenetresConsecutivesNeSeChevauchentPas(): void
    {
        $semaine = RaceWindow::fromDays(new \DateTimeImmutable('2026-08-03'), new \DateTimeImmutable('2026-08-09'));
        $suivante = RaceWindow::fromDays(new \DateTimeImmutable('2026-08-10'), new \DateTimeImmutable('2026-08-16'));

        $this->assertEquals($semaine->endsAt, $suivante->startsAt);

        $lundiMinuit = new \DateTimeImmutable('2026-08-10 00:00:00');
        $this->assertFalse($semaine->contains($lundiMinuit));
        $this->assertTrue($suivante->contains($lundiMinuit));
    }

    public function testFenetreDUnSeulJour(): void
    {
        $window = RaceWindow::fromDays(
            new \DateTimeImmutable('2026-08-05'),
            new \DateTimeImmutable('2026-08-05'),
        );

        $this->assertTrue($window->contains(new \DateTimeImmutable('2026-08-05 12:00:00')));
        $this->assertFalse($window->contains(new \DateTimeImmutable('2026-08-06 00:00:00')));
        $this->assertSame('2026-08-05', $window->endDate());
    }

    public function testLesDatesDuContratJsonRestentLeDernierJourInclus(): void
    {
        // Garde-fou de non-régression : ces deux chaînes sont exposées telles
        // quelles dans le JSON de /api/ranked-race depuis la livraison initiale.
        $window = RaceWindow::fromDays(
            new \DateTimeImmutable('2026-08-03'),
            new \DateTimeImmutable('2026-08-09'),
        );

        $this->assertSame('2026-08-03', $window->startDate());
        $this->assertSame('2026-08-09', $window->endDate());
    }

    public function testLeReportEstCaleSurLaCadenceDeCollecte(): void
    {
        $window = RaceWindow::fromDays(
            new \DateTimeImmutable('2026-08-03'),
            new \DateTimeImmutable('2026-08-09'),
        );

        $this->assertSame('2026-08-02 22:00:00', $window->carryInStart()->format('Y-m-d H:i:s'));
    }

    public function testIsCarryInNAccepteQueLesRelevesAnterieursEtImmediats(): void
    {
        $window = RaceWindow::fromDays(
            new \DateTimeImmutable('2026-08-03'),
            new \DateTimeImmutable('2026-08-09'),
        );

        // Le relevé de 23h30, une demi-heure avant la fenêtre : report valable.
        $this->assertTrue($window->isCarryIn(new \DateTimeImmutable('2026-08-02 23:30:00')));
        // Bornes : le début de la plage de report est inclus, la fenêtre exclue.
        $this->assertTrue($window->isCarryIn(new \DateTimeImmutable('2026-08-02 22:00:00')));
        // La veille au matin : trop ancien. Le segment le reliant au premier
        // relevé de la fenêtre crediterait à celle-ci les parties de la veille.
        $this->assertFalse($window->isCarryIn(new \DateTimeImmutable('2026-08-02 03:00:00')));
        // Un relevé DANS la fenêtre n'est pas un report.
        $this->assertFalse($window->isCarryIn(new \DateTimeImmutable('2026-08-03 00:00:00')));
    }

    public function testBetweenAccepteDesInstantsQuelconques(): void
    {
        $window = RaceWindow::between(
            new \DateTimeImmutable('2026-08-03 14:32:11'),
            new \DateTimeImmutable('2026-08-03 18:00:00'),
        );

        $this->assertTrue($window->contains(new \DateTimeImmutable('2026-08-03 14:32:11')));
        $this->assertFalse($window->contains(new \DateTimeImmutable('2026-08-03 18:00:00')));
    }
}
