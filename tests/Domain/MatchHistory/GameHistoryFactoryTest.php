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
        $this->assertSame(10, $game->kills);
        $this->assertSame(2,  $game->deaths);
        $this->assertSame(8,  $game->assists);
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