<?php

namespace App\Tests\Domain\LanChallenge;

use App\Domain\LanChallenge\LanQueue;
use App\Domain\LanChallenge\GameReport;
use App\Domain\LanChallenge\PlayerGameStats;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Le relevé vient d'un agent sur le poste d'un joueur (ADR-0005) : une donnée
 * aberrante doit être refusée à l'entrée du domaine, pas valider une carte.
 */
class PlayerGameStatsTest extends TestCase
{
    public function testLaParticipationEstPlafonneeACentPourCent(): void
    {
        // Relevé incohérent (plus de participations que de kills d'équipe) :
        // on plafonne plutôt que d'annoncer 125 % à l'écran.
        $stats = PlayerGameStatsBuilder::aPlayer()->withKills(10)->withAssists(15)->withTeamKills(20)->build();

        $this->assertSame(1.0, $stats->killParticipation());
    }

    public function testLaParticipationSansKillDEquipeVautZero(): void
    {
        $this->assertSame(0.0, PlayerGameStatsBuilder::aPlayer()->withTeamKills(0)->build()->killParticipation());
    }

    /** @return iterable<string, array{PlayerGameStatsBuilder}> */
    public static function aberrantStats(): iterable
    {
        yield 'kills négatifs' => [PlayerGameStatsBuilder::aPlayer()->withKills(-1)];
        yield 'morts négatives' => [PlayerGameStatsBuilder::aPlayer()->withDeaths(-1)];
        yield 'assistances négatives' => [PlayerGameStatsBuilder::aPlayer()->withAssists(-1)];
        yield 'solo kills négatifs' => [PlayerGameStatsBuilder::aPlayer()->withSoloKills(-1)];
        yield 'kills d\'équipe négatifs' => [PlayerGameStatsBuilder::aPlayer()->withTeamKills(-1)];
        yield 'aces négatifs' => [PlayerGameStatsBuilder::aPlayer()->withAces(-1)];
        yield 'série de 6 kills' => [PlayerGameStatsBuilder::aPlayer()->withLargestMultiKill(6)];
        yield 'série négative' => [PlayerGameStatsBuilder::aPlayer()->withLargestMultiKill(-1)];
    }

    #[DataProvider('aberrantStats')]
    public function testUnReleveAberrantEstRefuse(PlayerGameStatsBuilder $builder): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $builder->build();
    }

    public function testUnePartieDeDureeNegativeEstRefusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new GameReport('game-1', LanQueue::ARAM_MAYHEM, -1, []);
    }

    public function testUnReleveRetrouveChaqueJoueurParSonPuuid(): void
    {
        $report = new GameReport('game-1', LanQueue::ARAM_MAYHEM, 600, [
            PlayerGameStatsBuilder::aPlayer('puuid-a')->withKills(3)->build(),
            PlayerGameStatsBuilder::aPlayer('puuid-b')->withKills(7)->build(),
        ]);

        $this->assertSame(7, $report->statsFor('puuid-b')->kills);
        $this->assertNull($report->statsFor('puuid-absent'));
    }
}
