<?php

namespace App\Tests\Infrastructure\LanGame;

use App\Domain\LanChallenge\LanQueue;
use App\Domain\LanGame\Event\Ace;
use App\Domain\LanGame\Event\ChampionKill;
use App\Domain\LanGame\Event\FirstBlood;
use App\Domain\LanGame\Event\GameEnd;
use App\Domain\LanGame\Event\Multikill;
use App\Domain\LanGame\GameStatsCalculator;
use App\Domain\LanGame\InvalidReportException;
use App\Infrastructure\LanGame\LiveClientSnapshotParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Sur une vraie partie d'ARAM du chaos, anonymisée (tests/Fixtures/LiveClient) :
 * Joueur1 à Joueur5 chez ORDER, qui gagne ; le poste est celui de Joueur3.
 */
class LiveClientSnapshotParserTest extends TestCase
{
    /** @return array<string, mixed> */
    public static function realGame(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/LiveClient/aram-chaos-final.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testLitLaPartie(): void
    {
        $snapshot = LiveClientSnapshotParser::parse(self::realGame());

        $this->assertSame('KIWI', $snapshot->gameMode);
        $this->assertEqualsWithDelta(980.76, $snapshot->gameTimeSeconds, 0.01);
        $this->assertSame('Joueur3#EUW', $snapshot->activeRiotId);
        $this->assertCount(10, $snapshot->players);

        $first = $snapshot->players[0];
        $this->assertSame(['Joueur1#EUW', 'Joueur1', 'ORDER', 26, 9, 17], [$first->riotId, $first->gameName, $first->team, $first->kills, $first->deaths, $first->assists]);
    }

    public function testNeGardeQueLesEvenementsUtilesAuxCartes(): void
    {
        $snapshot = LiveClientSnapshotParser::parse(self::realGame());

        $this->assertCount(84, $snapshot->eventsOf(ChampionKill::class));
        $this->assertCount(25, $snapshot->eventsOf(Multikill::class));
        $this->assertCount(6, $snapshot->eventsOf(Ace::class));
        $this->assertCount(1, $snapshot->eventsOf(FirstBlood::class));
        $this->assertCount(84 + 25 + 6 + 1 + 1, $snapshot->events, 'Tourelles, inhibiteurs… sont ignorés.');
        $this->assertTrue($snapshot->gameEnd()?->activePlayerWon);
        $this->assertSame('Joueur2', $snapshot->eventsOf(FirstBlood::class)[0]->recipientName);
        $this->assertSame(['Joueur1', 4], [$snapshot->eventsOf(Multikill::class)[6]->killerName, $snapshot->eventsOf(Multikill::class)[6]->killStreak]);
        $this->assertSame('Joueur1', $snapshot->eventsOf(Ace::class)[0]->acerName);
    }

    public function testLesStatistiquesDeLaVraiePartie(): void
    {
        $report = GameStatsCalculator::report(
            'game-1',
            LanQueue::ARAM_MAYHEM,
            LiveClientSnapshotParser::parse(self::realGame()),
            ['joueur1#euw' => 'puuid-1', 'joueur2#euw' => 'puuid-2', 'joueur3#euw' => 'puuid-3', 'joueur6#euw' => 'puuid-6', 'joueur8#euw' => 'puuid-8'],
        );

        $this->assertSame(980, $report->durationSeconds);

        $carry = $report->statsFor('puuid-1');
        $this->assertSame(5, $carry->largestMultiKill, 'Le penta.');
        $this->assertSame(2, $carry->soloKills);
        $this->assertSame(47, $carry->teamKills);
        $this->assertSame(2, $carry->aces);
        $this->assertTrue($carry->win);
        $this->assertFalse($carry->firstBlood);
        $this->assertFalse($carry->mostDeathsInGame);

        $this->assertTrue($report->statsFor('puuid-2')->firstBlood);
        $this->assertSame(2, $report->statsFor('puuid-2')->largestMultiKill);
        $this->assertSame(1, $report->statsFor('puuid-3')->largestMultiKill, 'Trois kills isolés.');
        $this->assertSame(1, $report->statsFor('puuid-3')->aces);

        $feeder = $report->statsFor('puuid-6');
        $this->assertFalse($feeder->win);
        $this->assertTrue($feeder->mostDeathsInGame, '12 morts, le record de la partie.');
        $this->assertSame(37, $feeder->teamKills);
        $this->assertSame(3, $feeder->soloKills);
        $this->assertSame(1, $report->statsFor('puuid-8')->soloKills);

        $this->assertSame(18, $report->statsFor('puuid-3')->mostAssistsOnOneAlly);
        $this->assertSame(4, $report->statsFor('puuid-2')->avengedAllies);

        $this->assertSame(6, $carry->longestKillStreak);
        $this->assertSame(8, $carry->mostKillsOnOneEnemy, 'Joueur1 a tué 8 fois Joueur6.');
        $this->assertTrue($carry->mostKillsAmongLan, '26 kills, loin devant les autres joueurs de la LAN.');
        $this->assertFalse($report->statsFor('puuid-2')->mostKillsAmongLan);
    }

    public function testLeResultatDeFinDePartie(): void
    {
        $this->assertFalse(LiveClientSnapshotParser::parse(self::withGameEnd('Lose'))->gameEnd()?->activePlayerWon);
        $this->assertNull(LiveClientSnapshotParser::parse(self::withGameEnd('Remake'))->gameEnd()?->activePlayerWon);
        $this->assertInstanceOf(GameEnd::class, LiveClientSnapshotParser::parse(self::withGameEnd('Remake'))->gameEnd());
    }

    public function testUnKillParUneTourelleEstLuSansAssistant(): void
    {
        $liveClient = self::realGame();
        $liveClient['events']['Events'] = [['EventName' => 'ChampionKill', 'KillerName' => 'Turret_T1_C_05_A', 'VictimName' => 'Joueur1']];

        $kill = LiveClientSnapshotParser::parse($liveClient)->eventsOf(ChampionKill::class)[0];

        $this->assertSame(['Turret_T1_C_05_A', 'Joueur1', [], 0.0], [$kill->killerName, $kill->victimName, $kill->assisterNames, $kill->time]);
    }

    /** @return iterable<string, array{callable(array<string, mixed>): array<string, mixed>}> */
    public static function malformed(): iterable
    {
        yield 'sans gameData' => [static function (array $liveClient) { unset($liveClient['gameData']); return $liveClient; }];
        yield 'sans activePlayer' => [static function (array $liveClient) { unset($liveClient['activePlayer']); return $liveClient; }];
        yield 'sans allPlayers' => [static function (array $liveClient) { unset($liveClient['allPlayers']); return $liveClient; }];
        yield 'allPlayers vide' => [static function (array $liveClient) { $liveClient['allPlayers'] = []; return $liveClient; }];
        yield 'sans events' => [static function (array $liveClient) { unset($liveClient['events']); return $liveClient; }];
        yield 'sans events.Events' => [static function (array $liveClient) { $liveClient['events'] = []; return $liveClient; }];
        yield 'gameMode vide' => [static function (array $liveClient) { $liveClient['gameData']['gameMode'] = ''; return $liveClient; }];
        yield 'gameTime en texte' => [static function (array $liveClient) { $liveClient['gameData']['gameTime'] = '12'; return $liveClient; }];
        yield 'gameTime négatif' => [static function (array $liveClient) { $liveClient['gameData']['gameTime'] = -1; return $liveClient; }];
        yield 'joueur du poste absent de la partie' => [static function (array $liveClient) { $liveClient['activePlayer']['riotId'] = 'Autre#EUW'; return $liveClient; }];
        yield 'joueur qui n\'est pas un objet' => [static function (array $liveClient) { $liveClient['allPlayers'][4] = 'Joueur5'; return $liveClient; }];
        yield 'joueur sans scores' => [static function (array $liveClient) { unset($liveClient['allPlayers'][4]['scores']); return $liveClient; }];
        yield 'joueur sans équipe' => [static function (array $liveClient) { unset($liveClient['allPlayers'][4]['team']); return $liveClient; }];
        yield 'joueur sans pseudo' => [static function (array $liveClient) { unset($liveClient['allPlayers'][4]['riotIdGameName']); return $liveClient; }];
        yield 'morts en texte' => [static function (array $liveClient) { $liveClient['allPlayers'][4]['scores']['deaths'] = '3'; return $liveClient; }];
    }

    /** @param callable(array<string, mixed>): array<string, mixed> $breakIt */
    #[DataProvider('malformed')]
    public function testUnReleveIncompletEstRefuse(callable $breakIt): void
    {
        $this->expectException(InvalidReportException::class);

        LiveClientSnapshotParser::parse($breakIt(self::realGame()));
    }

    public function testUnEvenementIllisibleEstIgnore(): void
    {
        $liveClient = self::realGame();
        $liveClient['events']['Events'] = ['pas un objet', ['EventName' => 'DragonKill'], ['sans nom']];

        $this->assertSame([], LiveClientSnapshotParser::parse($liveClient)->events);
    }

    /** @return array<string, mixed> */
    private static function withGameEnd(string $result): array
    {
        $liveClient = self::realGame();
        $liveClient['events']['Events'] = [['EventName' => 'GameEnd', 'Result' => $result]];

        return $liveClient;
    }
}
