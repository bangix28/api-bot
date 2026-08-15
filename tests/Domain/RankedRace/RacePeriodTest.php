<?php

namespace App\Tests\Domain\RankedRace;

use App\Domain\RankedRace\InvalidRankedRaceParameterException;
use App\Domain\RankedRace\RacePeriod;
use PHPUnit\Framework\TestCase;

class RacePeriodTest extends TestCase
{
    public function testFromQueryParam(): void
    {
        $this->assertSame(RacePeriod::WEEK, RacePeriod::fromQueryParam('week'));
        $this->assertSame(RacePeriod::MONTH, RacePeriod::fromQueryParam('month'));
    }

    public function testFromQueryParamInvalide(): void
    {
        $this->expectException(InvalidRankedRaceParameterException::class);

        RacePeriod::fromQueryParam('year');
    }

    public function testFenetreSemaineIsoDuLundiAuDimanche(): void
    {
        // Mercredi 5 août 2026
        $window = RacePeriod::WEEK->windowFor(new \DateTimeImmutable('2026-08-05'));

        $this->assertSame('2026-08-03', $window->startDate());
        $this->assertSame('2026-08-09', $window->endDate());
    }

    public function testFenetreSemaineUnDimancheResteDansLaSemaineIso(): void
    {
        // Dimanche 9 août : la semaine ISO commence le lundi précédent, pas le lendemain.
        $window = RacePeriod::WEEK->windowFor(new \DateTimeImmutable('2026-08-09'));

        $this->assertSame('2026-08-03', $window->startDate());
        $this->assertSame('2026-08-09', $window->endDate());
    }

    public function testFenetreSemaineAChevalSurDeuxAnnees(): void
    {
        // Jeudi 1er janvier 2026 : la semaine a commencé le lundi 29 décembre 2025.
        $window = RacePeriod::WEEK->windowFor(new \DateTimeImmutable('2026-01-01'));

        $this->assertSame('2025-12-29', $window->startDate());
        $this->assertSame('2026-01-04', $window->endDate());
    }

    public function testFenetreMoisCivil(): void
    {
        $window = RacePeriod::MONTH->windowFor(new \DateTimeImmutable('2026-02-15'));

        $this->assertSame('2026-02-01', $window->startDate());
        $this->assertSame('2026-02-28', $window->endDate());
    }

    public function testUneHeureNonNulleDonneLaMemeFenetreQuAMinuit(): void
    {
        // « first day of this month » conserve l'heure du jour de référence :
        // sans normalisation, la fenêtre du mois démarrerait à 14h30.
        $aMidi = RacePeriod::MONTH->windowFor(new \DateTimeImmutable('2026-02-15 14:30:00'));
        $aMinuit = RacePeriod::MONTH->windowFor(new \DateTimeImmutable('2026-02-15 00:00:00'));

        $this->assertEquals($aMinuit, $aMidi);
        $this->assertSame('2026-02-01 00:00:00', $aMidi->startsAt->format('Y-m-d H:i:s'));
    }

    public function testLaSemaineCouvreLaFinDuDimanche(): void
    {
        $window = RacePeriod::WEEK->windowFor(new \DateTimeImmutable('2026-08-05'));

        // Une partie terminée dimanche à 23h30 appartient bien à cette semaine.
        $this->assertTrue($window->contains(new \DateTimeImmutable('2026-08-09 23:30:00')));
        $this->assertFalse($window->contains(new \DateTimeImmutable('2026-08-10 00:00:00')));
    }

    public function testSeuilsDeQualification(): void
    {
        $this->assertSame(5, RacePeriod::WEEK->minGamesToQualify());
        $this->assertSame(15, RacePeriod::MONTH->minGamesToQualify());
    }
}
