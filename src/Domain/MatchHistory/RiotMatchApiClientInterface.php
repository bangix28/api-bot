<?php

namespace App\Domain\MatchHistory;

interface RiotMatchApiClientInterface
{
    /**
     * Identifiants des derniers matchs d'un compte dans une file.
     * La file est passée explicitement : la collecte ne connaissait que la solo,
     * ce qui rendait toute règle métier fondée sur les matchs aveugle à la flex.
     *
     * @return array<string>
     */
    public function getMatchIds(string $puuid, MatchQueue $queue, ?int $since): array;

    public function getMatch(string $matchId): ?MatchData;
}