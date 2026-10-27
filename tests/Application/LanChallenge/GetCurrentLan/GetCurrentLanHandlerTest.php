<?php

namespace App\Tests\Application\LanChallenge\GetCurrentLan;

use App\Application\LanChallenge\GetCurrentLan\CurrentLanView;
use App\Application\LanChallenge\GetCurrentLan\GetCurrentLanHandler;
use App\Domain\LanChallenge\LanSummary;
use App\Domain\Player\TrackedRiotAccount;
use App\Tests\Application\LanChallenge\LanChallengeFixture;
use App\Tests\Domain\Shared\FixedClock;
use PHPUnit\Framework\TestCase;

/**
 * LAN 7 (samedi 14:00 → dimanche 04:00) : Alpha, Bravo, Charlie.
 * LAN 8, la semaine suivante : Alpha (avec un second compte) et Delta, dont le
 * premier compte n'est inscrit nulle part.
 */
class GetCurrentLanHandlerTest extends TestCase
{
    private const string DELTA = '1004';

    private LanChallengeFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new LanChallengeFixture();
        $this->fixture->lans->add(new LanSummary(
            8,
            'LAN d\'hiver',
            new \DateTimeImmutable('2026-11-14 14:00'),
            new \DateTimeImmutable('2026-11-15 04:00'),
            ['puuid-a' => 'Alpha#EUW', 'puuid-a2' => 'AlphaSmurf#EUW', 'puuid-d' => 'Delta#EUW'],
        ));
        $this->fixture->accounts
            ->add(new TrackedRiotAccount('AlphaSmurf#EUW', 'puuid-a2', LanChallengeFixture::ALPHA))
            ->add(new TrackedRiotAccount('DeltaAlt#EUW', 'puuid-d0', self::DELTA))
            ->add(new TrackedRiotAccount('Delta#EUW', 'puuid-d', self::DELTA))
            ->add(new TrackedRiotAccount('Echo#EUW', 'puuid-e', '1005'));
    }

    private function currentLanAt(string $dateTime, string $discordId): ?CurrentLanView
    {
        return (new GetCurrentLanHandler($this->fixture->accounts, $this->fixture->lans, new FixedClock(new \DateTimeImmutable($dateTime))))
            ->handle($discordId);
    }

    public function testPendantUneLanCEstElleQuiEstRenvoyee(): void
    {
        $view = $this->currentLanAt('2026-11-07 21:00', LanChallengeFixture::ALPHA);

        $this->assertSame(LanChallengeFixture::LAN, $view->lan->lanId);
        $this->assertTrue($view->inProgress);
        $this->assertSame(['Alpha#EUW'], $view->riotIds, 'Seuls les comptes inscrits à cette LAN.');
    }

    public function testAvantLeDebutLaLanEstAVenir(): void
    {
        $view = $this->currentLanAt('2026-11-07 13:59', LanChallengeFixture::BRAVO);

        $this->assertSame(LanChallengeFixture::LAN, $view->lan->lanId);
        $this->assertFalse($view->inProgress);
    }

    public function testLaLanEstEnCoursDesSonHeureDeDebut(): void
    {
        $this->assertTrue($this->currentLanAt('2026-11-07 14:00', LanChallengeFixture::BRAVO)->inProgress);
    }

    public function testApresLaFinOnPasseALaSuivante(): void
    {
        $view = $this->currentLanAt('2026-11-08 04:01', LanChallengeFixture::ALPHA);

        $this->assertSame(8, $view->lan->lanId);
        $this->assertFalse($view->inProgress);
        $this->assertSame(['Alpha#EUW', 'AlphaSmurf#EUW'], $view->riotIds);
    }

    public function testUnJoueurNeVoitQueLesLanOuIlEstInscrit(): void
    {
        $view = $this->currentLanAt('2026-11-07 21:00', self::DELTA);

        $this->assertSame(8, $view->lan->lanId);
        // Une liste, pas un tableau aux clés trouées : sinon le JSON en ferait un objet.
        $this->assertSame(['Delta#EUW'], $view->riotIds);
    }

    public function testSansLanEnCoursNiAVenirIlNYARien(): void
    {
        $this->assertNull($this->currentLanAt('2026-11-07 21:00', '1005'), 'Echo n\'est inscrit nulle part.');
        $this->assertNull($this->currentLanAt('2026-11-08 04:01', LanChallengeFixture::BRAVO), 'La seule LAN de Bravo est terminée.');
        $this->assertNull($this->currentLanAt('2026-11-07 21:00', '9999'), 'Aucun compte lié.');
    }
}
