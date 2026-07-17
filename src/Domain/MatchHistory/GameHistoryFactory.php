<?php

namespace App\Domain\MatchHistory;

class  GameHistoryFactory
{
    const int SECONDS_PER_MINUTE = 60;
    const int MILLISECONDS_PER_SECOND = 1000;

    /**
     * @throws \DateMalformedStringException
     */
    public static function fromMatchInfo(
        array  $matchInfo,
        string $playerPuuid,
    ): GameHistoryEntity
    {
        $dataParticipantFromMatch = self::extractedPlayerDataFromPuuid($matchInfo, $playerPuuid);
        $secondes = intdiv($matchInfo['gameEndTimestamp'], self::MILLISECONDS_PER_SECOND);

        return new GameHistoryEntity(
            $dataParticipantFromMatch['win'],
            $dataParticipantFromMatch['championId'],
            $dataParticipantFromMatch['kills'],
            $dataParticipantFromMatch['deaths'],
            $dataParticipantFromMatch['assists'],
            new \DateTimeImmutable('@' . $secondes),
            intdiv($matchInfo['gameDuration'], self::SECONDS_PER_MINUTE)
        );
    }

    /**
     * @param array $matchInfo
     * @param string $playerPuuid
     * @return array
     */
    private static function extractedPlayerDataFromPuuid(array $matchInfo, string $playerPuuid): array
    {
        $playerData = array_find(
            $matchInfo['participants'],
            static fn($dataParticipant) => $dataParticipant['puuid'] === $playerPuuid
        );

        if ($playerData === null)
        {
            throw new ParticipantsNotFoundException();
        }

        return $playerData;
    }
}


