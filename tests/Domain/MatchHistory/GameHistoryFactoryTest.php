<?php

namespace App\Tests\Domain\MatchHistory;

use App\Domain\MatchHistory\ParticipantsNotFoundException;
use PHPUnit\Framework\TestCase;
use App\Domain\MatchHistory\GameHistoryFactory;

class GameHistoryFactoryTest extends TestCase
{
    public function testExtractsTargetParticipantStats(): void
    {
        $info = [
            'gameEndTimestamp' => 1700000000000,
            'gameDuration'     => 1800,
            'participants' => [
                ['puuid' => 'autre',   'win' => false, 'championId' => 1,  'kills' => 0,  'deaths' => 9, 'assists' => 1],
                ['puuid' => 'puuid-1', 'win' => true,  'championId' => 64, 'kills' => 10, 'deaths' => 2, 'assists' => 8],
            ],
        ];

        $game = GameHistoryFactory::fromMatchInfo($info, 'puuid-1', ['kda']);

        $this->assertTrue($game->isWin);
        $this->assertSame(64, $game->championId);
        $this->assertSame(10, $game->kills);
        $this->assertSame(2,  $game->deaths);
        $this->assertSame(8,  $game->assists);
    }

    public function testConvertsGameEndTimestampToDate()
    {
        $info = [
            'gameEndTimestamp' => 1700000000000,
            'gameDuration'     => 1800,
            'participants' => [
                ['puuid' => 'autre',   'win' => false, 'championId' => 1,  'kills' => 0,  'deaths' => 9, 'assists' => 1, 'challenges' => []],
                ['puuid' => 'puuid-1', 'win' => true,  'championId' => 64, 'kills' => 10, 'deaths' => 2, 'assists' => 8, 'challenges' => ['kda' => 9]],
            ],
        ];

        $game = GameHistoryFactory::fromMatchInfo($info, 'puuid-1', ['kda']);
        $this->assertEquals(new \DateTimeImmutable('@1700000000'), $game->gameEnd);
    }

    public function testConvertsDurationToMinutes()
    {
        $info = [
            'gameEndTimestamp' => 1700000000000,
            'gameDuration'     => 1800,
            'participants' => [
                ['puuid' => 'autre',   'win' => false, 'championId' => 1,  'kills' => 0,  'deaths' => 9, 'assists' => 1, 'challenges' => []],
                ['puuid' => 'puuid-1', 'win' => true,  'championId' => 64, 'kills' => 10, 'deaths' => 2, 'assists' => 8, 'challenges' => ['kda' => 9]],
            ],
        ];

        $game = GameHistoryFactory::fromMatchInfo($info, 'puuid-1', ['kda']);
        $this->assertEquals(30, $game->gameDuration);
    }


    public function testCreateGameHistoryFactoryWithMissingParticipantsPuiid(): void
    {
        $info = [
            'gameEndTimestamp' => 1700000000000,
            'gameDuration'     => 1800,
            'participants' => [
                ['puuid' => 'autre',   'win' => false, 'championId' => 1,  'kills' => 0,  'deaths' => 9, 'assists' => 1, 'challenges' => []],
            ],
        ];

        $this->expectException(ParticipantsNotFoundException::class);
        GameHistoryFactory::fromMatchInfo($info, 'puuid-1', ['kda']);
    }

}