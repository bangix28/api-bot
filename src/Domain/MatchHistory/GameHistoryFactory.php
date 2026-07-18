<?php

namespace App\Domain\MatchHistory;

class  GameHistoryFactory
{
    const int SECONDS_PER_MINUTE = 60;
    const int MILLISECONDS_PER_SECOND = 1000;

    /**
     * @param MatchData $matchInfo
     * @param string $playerPuuid
     * @return GameHistoryEntity
     * @throws \Exception
     */
    public static function fromMatchInfo(
        MatchData  $matchInfo,
        string $playerPuuid,
    ): GameHistoryEntity
    {
        $dataParticipantFromMatch = self::extractedPlayerDataFromPuuid($matchInfo, $playerPuuid);
        $secondes = intdiv($matchInfo->gameEndTimeStamp, self::MILLISECONDS_PER_SECOND);

        return new GameHistoryEntity(
            $dataParticipantFromMatch->win,
            $dataParticipantFromMatch->championId,
            $dataParticipantFromMatch->kills,
            $dataParticipantFromMatch->deaths,
            $dataParticipantFromMatch->assists,
            new \DateTimeImmutable('@' . $secondes),
            intdiv($matchInfo->gameDuration, self::SECONDS_PER_MINUTE),
            $playerPuuid,
        );
    }

    /**
     * @param MatchData $matchInfo
     * @param string $playerPuuid
     * @return ParticipantData
     */
    private static function extractedPlayerDataFromPuuid(MatchData $matchInfo, string $playerPuuid): ParticipantData
    {
        $playerData = array_find(
            $matchInfo->participants,
            static fn(ParticipantData $dataParticipant) => $dataParticipant->puuid === $playerPuuid
        );

        if ($playerData === null)
        {
            throw new ParticipantsNotFoundException();
        }

        return $playerData;
    }
}


