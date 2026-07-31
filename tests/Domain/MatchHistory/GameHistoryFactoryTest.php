<?php

namespace App\Tests\Domain\MatchHistory;

use App\Domain\MatchHistory\ParticipantsNotFoundException;
use PHPUnit\Framework\TestCase;
use App\Domain\MatchHistory\GameHistoryFactory;

class GameHistoryFactoryTest extends TestCase
{
    public function testExtractsTargetParticipantStats(): void
    {
        $match = MatchDataBuilder::aMatch()
            ->withParticipantData(ParticipantDataBuilder::aParticipant()->build())
            ->withParticipantData(ParticipantDataBuilder::aParticipant()->withPuuid('autre')->build())
            ->build();


        $game = GameHistoryFactory::fromMatchInfo($match, 'puuid-1');

        $this->assertTrue($game->isWin);
        $this->assertSame(64, $game->championId);
        $this->assertSame(10, $game->score->kills);
        $this->assertSame(2,  $game->score->deaths);
        $this->assertSame(8,  $game->score->assists);
        $this->assertSame('puuid-1', $game->puuid);
    }

    public function testPassesThroughMatchContextAndParticipantDetails(): void
    {
        $participant = ParticipantDataBuilder::aParticipant()
            ->withChampionName('LeeSin')
            ->withTeamPosition('JUNGLE')
            ->build();

        $match = MatchDataBuilder::aMatch()
            ->withMatchId('EUW1_42')
            ->withQueueId(420)
            ->withParticipantData($participant)
            ->build();

        $game = GameHistoryFactory::fromMatchInfo($match, 'puuid-1');

        $this->assertSame('EUW1_42', $game->matchId);
        $this->assertSame(420, $game->queueId);
        $this->assertSame('LeeSin', $game->championName);
        $this->assertSame('JUNGLE', $game->teamPosition);
        // Les VOs du participant sont transmis tels quels, sans copie ni transformation
        $this->assertSame($participant->score, $game->score);
        $this->assertSame($participant->build, $game->build);
        $this->assertSame($participant->combat, $game->combat);
        $this->assertSame($participant->performance, $game->performance);
    }

    public function testConvertsGameEndTimestampToDate()
    {
        $match = MatchDataBuilder::aMatch()
            ->withParticipantData(ParticipantDataBuilder::aParticipant()->build())
            ->withParticipantData(ParticipantDataBuilder::aParticipant()->withPuuid('autre')->build())
            ->build();

        $game = GameHistoryFactory::fromMatchInfo($match, 'puuid-1');
        $this->assertEquals(new \DateTimeImmutable('@1700000000'), $game->gameEnd);
    }

    public function testConvertsDurationToMinutes()
    {
        $match = MatchDataBuilder::aMatch()
            ->withParticipantData(ParticipantDataBuilder::aParticipant()->build())
            ->withParticipantData(ParticipantDataBuilder::aParticipant()->withPuuid('autre')->build())
            ->build();

        $game = GameHistoryFactory::fromMatchInfo($match, 'puuid-1');
        $this->assertEquals(30, $game->gameDuration);
    }


    public function testCreateGameHistoryFactoryWithMissingParticipantsPuiid(): void
    {
        $match = MatchDataBuilder::aMatch()
            ->withParticipantData(ParticipantDataBuilder::aParticipant()->withPuuid('autre')->build())
            ->build();

        $this->expectException(ParticipantsNotFoundException::class);
        GameHistoryFactory::fromMatchInfo($match, 'puuid-1');
    }

}
