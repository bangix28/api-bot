<?php

namespace App\Domain\LanGame;

use App\Domain\LanChallenge\GameReport;
use App\Domain\LanChallenge\LanQueue;
use App\Domain\LanChallenge\PlayerGameStats;
use App\Domain\LanGame\Event\Ace;
use App\Domain\LanGame\Event\ChampionKill;
use App\Domain\LanGame\Event\FirstBlood;
use App\Domain\LanGame\Event\Multikill;

/**
 * Tire d'un relevé de fin de partie les statistiques dont les cartes ont besoin
 * (ADR-0005 : « l'agent transporte, le serveur juge »). Seuls les participants
 * de la LAN présents dans la partie figurent dans le rapport.
 */
final class GameStatsCalculator
{
    private const int AVENGE_WINDOW_SECONDS = 10;

    /**
     * @param array<string, string> $participantPuuids puuid des participants de la LAN, indexés par Riot ID en minuscules
     */
    public static function report(string $gameId, LanQueue $queue, GameSnapshot $snapshot, array $participantPuuids): GameReport
    {
        $stats = [];
        foreach ($snapshot->players as $player) {
            $puuid = $participantPuuids[mb_strtolower($player->riotId)] ?? null;
            if ($puuid !== null) {
                $stats[] = self::statsOf($puuid, $player, $snapshot);
            }
        }

        return new GameReport($gameId, $queue, (int) $snapshot->gameTimeSeconds, $stats);
    }

    private static function statsOf(string $puuid, ObservedPlayer $player, GameSnapshot $snapshot): PlayerGameStats
    {
        $killsBy = array_filter($snapshot->eventsOf(ChampionKill::class), static fn(ChampionKill $kill) => $kill->killerName === $player->gameName);
        $streaks = array_map(
            static fn(Multikill $multikill) => $multikill->killStreak,
            array_filter($snapshot->eventsOf(Multikill::class), static fn(Multikill $multikill) => $multikill->killerName === $player->gameName),
        );

        return new PlayerGameStats(
            $puuid,
            $player->kills,
            $player->deaths,
            $player->assists,
            // Pas d'événement Multikill pour un kill isolé : une série de 1 dès le premier kill.
            min(5, max([...$streaks, $player->kills > 0 ? 1 : 0])),
            count(array_filter($killsBy, static fn(ChampionKill $kill) => $kill->assisterNames === [])),
            self::teamKills($player->team, $snapshot),
            count(array_filter($snapshot->eventsOf(Ace::class), static fn(Ace $ace) => $ace->acerName === $player->gameName)),
            self::won($player, $snapshot),
            self::firstBlood($player, $snapshot),
            self::hasMostDeaths($player, $snapshot),
            self::mostAssistsOnOneAlly($player, $snapshot),
            self::avengedAllies($player, $snapshot),
        );
    }

    private static function mostAssistsOnOneAlly(ObservedPlayer $player, GameSnapshot $snapshot): int
    {
        $assistsByAlly = [];
        foreach ($snapshot->eventsOf(ChampionKill::class) as $kill) {
            if (in_array($player->gameName, $kill->assisterNames, true) && $snapshot->playerByName($kill->killerName) !== null) {
                $assistsByAlly[$kill->killerName] = ($assistsByAlly[$kill->killerName] ?? 0) + 1;
            }
        }

        return max([0, ...array_values($assistsByAlly)]);
    }

    /**
     * Venger un coéquipier : tuer son tueur dans les AVENGE_WINDOW_SECONDS qui
     * suivent sa mort. Une même mort ne se venge qu'une fois.
     */
    private static function avengedAllies(ObservedPlayer $player, GameSnapshot $snapshot): int
    {
        $kills = $snapshot->eventsOf(ChampionKill::class);
        $allyDeaths = array_filter(
            $kills,
            static fn(ChampionKill $kill) => $kill->victimName !== $player->gameName
                && $snapshot->playerByName($kill->victimName)?->team === $player->team,
        );

        $avenged = 0;
        foreach ($kills as $kill) {
            if ($kill->killerName !== $player->gameName) {
                continue;
            }

            foreach ($allyDeaths as $index => $allyDeath) {
                $delay = $kill->time - $allyDeath->time;
                if ($allyDeath->killerName === $kill->victimName && $delay >= 0 && $delay <= self::AVENGE_WINDOW_SECONDS) {
                    $avenged++;
                    unset($allyDeaths[$index]);
                    break;
                }
            }
        }

        return $avenged;
    }

    private static function teamKills(string $team, GameSnapshot $snapshot): int
    {
        return array_sum(array_map(
            static fn(ObservedPlayer $teammate) => $teammate->kills,
            array_filter($snapshot->players, static fn(ObservedPlayer $teammate) => $teammate->team === $team),
        ));
    }

    /** GameEnd donne le résultat du joueur du poste : son équipe a gagné ou perdu, l'autre l'inverse. */
    private static function won(ObservedPlayer $player, GameSnapshot $snapshot): ?bool
    {
        $activePlayerWon = $snapshot->gameEnd()?->activePlayerWon;
        if ($activePlayerWon === null) {
            return null;
        }

        $sameTeam = $player->team === $snapshot->playerByRiotId($snapshot->activeRiotId)->team;

        return $sameTeam ? $activePlayerWon : !$activePlayerWon;
    }

    private static function firstBlood(ObservedPlayer $player, GameSnapshot $snapshot): ?bool
    {
        $firstBlood = $snapshot->eventsOf(FirstBlood::class)[0] ?? null;
        if ($firstBlood !== null) {
            return $firstBlood->recipientName === $player->gameName;
        }

        // Aucun premier sang dans un journal sans aucun kill : personne ne l'a eu.
        // S'il y a eu des kills sans l'événement, on ne sait pas.
        return $snapshot->eventsOf(ChampionKill::class) === [] ? false : null;
    }

    /**
     * Égalité comprise : deux joueurs à égalité de morts ont tous deux « le plus
     * de morts ». Mais il en faut au moins une : sinon, dans une partie sans
     * mort, tout le monde réussirait Gameplay Shaco.
     */
    private static function hasMostDeaths(ObservedPlayer $player, GameSnapshot $snapshot): bool
    {
        return $player->deaths > 0
            && $player->deaths === max(array_map(static fn(ObservedPlayer $anyPlayer) => $anyPlayer->deaths, $snapshot->players));
    }
}
