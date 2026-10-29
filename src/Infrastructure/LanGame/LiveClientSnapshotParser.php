<?php

namespace App\Infrastructure\LanGame;

use App\Domain\LanGame\Event\Ace;
use App\Domain\LanGame\Event\ChampionKill;
use App\Domain\LanGame\Event\FirstBlood;
use App\Domain\LanGame\Event\GameEnd;
use App\Domain\LanGame\Event\Multikill;
use App\Domain\LanGame\Event\ObservedEvent;
use App\Domain\LanGame\GameSnapshot;
use App\Domain\LanGame\InvalidReportException;
use App\Domain\LanGame\ObservedPlayer;

/**
 * Traduit la réponse brute de la Live Client Data API (`allgamedata`) en
 * GameSnapshot. Seul endroit qui connaît le format de Riot : s'il change, rien
 * d'autre ne bouge. Les événements dont les cartes n'ont pas besoin
 * (tourelles, dragons…) sont ignorés.
 */
final class LiveClientSnapshotParser
{
    /**
     * @param array<string, mixed> $liveClient
     * @param ?int $queueId file lue par l'agent dans le client LoL, hors de la Live Client Data API
     */
    public static function parse(array $liveClient, ?int $queueId = null): GameSnapshot
    {
        $gameData = self::section($liveClient, 'gameData');
        $activePlayer = self::section($liveClient, 'activePlayer');
        $allPlayers = $liveClient['allPlayers'] ?? null;
        $events = self::section($liveClient, 'events')['Events'] ?? null;

        // Une liste de joueurs vide est refusée par GameSnapshot : le joueur du poste doit y figurer.
        if (!is_array($allPlayers) || !is_array($events)) {
            throw new InvalidReportException('liveClient doit contenir allPlayers et events.Events.');
        }

        try {
            return new GameSnapshot(
                self::string($gameData, 'gameMode'),
                (float) self::number($gameData, 'gameTime'),
                self::string($activePlayer, 'riotId'),
                array_map(self::player(...), $allPlayers),
                array_values(array_filter(array_map(self::event(...), $events))),
                $queueId,
            );
        } catch (\InvalidArgumentException $exception) {
            throw new InvalidReportException($exception->getMessage(), previous: $exception);
        }
    }

    /** @param array<string, mixed> $player */
    private static function player(mixed $player): ObservedPlayer
    {
        if (!is_array($player)) {
            throw new InvalidReportException('Chaque entrée de allPlayers doit être un objet.');
        }
        $scores = self::section($player, 'scores');

        return new ObservedPlayer(
            self::string($player, 'riotId'),
            self::string($player, 'riotIdGameName'),
            self::string($player, 'team'),
            (int) self::number($scores, 'kills'),
            (int) self::number($scores, 'deaths'),
            (int) self::number($scores, 'assists'),
        );
    }

    private static function event(mixed $event): ?ObservedEvent
    {
        if (!is_array($event)) {
            return null;
        }

        return match ($event['EventName'] ?? null) {
            'ChampionKill' => new ChampionKill(
                (string) ($event['KillerName'] ?? ''),
                (string) ($event['VictimName'] ?? ''),
                array_map('strval', is_array($event['Assisters'] ?? null) ? $event['Assisters'] : []),
                (float) ($event['EventTime'] ?? 0),
            ),
            'Multikill' => new Multikill((string) ($event['KillerName'] ?? ''), (int) ($event['KillStreak'] ?? 0)),
            'Ace' => new Ace((string) ($event['Acer'] ?? '')),
            'FirstBlood' => new FirstBlood((string) ($event['Recipient'] ?? '')),
            'GameEnd' => new GameEnd(match ($event['Result'] ?? null) {
                'Win' => true,
                // Seul « Win » a été observé (ADR-0005) : « Lose » est la valeur attendue.
                'Lose' => false,
                default => null,
            }),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private static function section(array $source, string $key): array
    {
        $section = $source[$key] ?? null;
        if (!is_array($section)) {
            throw new InvalidReportException(sprintf('Champ « %s » absent ou mal formé.', $key));
        }

        return $section;
    }

    /** @param array<string, mixed> $source */
    private static function string(array $source, string $key): string
    {
        $value = $source[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new InvalidReportException(sprintf('Champ « %s » absent ou vide.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $source */
    private static function number(array $source, string $key): int|float
    {
        $value = $source[$key] ?? null;
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidReportException(sprintf('Champ « %s » absent ou non numérique.', $key));
        }

        return $value;
    }
}
