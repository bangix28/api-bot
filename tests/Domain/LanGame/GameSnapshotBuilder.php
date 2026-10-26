<?php

namespace App\Tests\Domain\LanGame;

use App\Domain\LanGame\Event\ObservedEvent;
use App\Domain\LanGame\GameSnapshot;
use App\Domain\LanGame\ObservedPlayer;

/**
 * Une partie d'ARAM du chaos à 10 : Alpha#EUW et Bravo#EUW chez ORDER,
 * Charlie#EUW chez CHAOS, les autres sont des inconnus. Joueur du poste : Alpha.
 * Scores à zéro, journal vide, 30 s de jeu.
 */
final class GameSnapshotBuilder
{
    private string $gameMode = 'KIWI';
    private float $gameTimeSeconds = 30.0;
    private string $activeRiotId = 'Alpha#EUW';
    /** @var array<string, ObservedPlayer> indexés par Riot ID */
    private array $players = [];
    /** @var ObservedEvent[] */
    private array $events = [];

    private function __construct()
    {
        $roster = [
            'Alpha#EUW' => 'ORDER', 'Bravo#EUW' => 'ORDER', 'Inconnu1#EUW' => 'ORDER', 'Inconnu2#EUW' => 'ORDER', 'Inconnu3#EUW' => 'ORDER',
            'Charlie#EUW' => 'CHAOS', 'Inconnu4#EUW' => 'CHAOS', 'Inconnu5#EUW' => 'CHAOS', 'Inconnu6#EUW' => 'CHAOS', 'Inconnu7#EUW' => 'CHAOS',
        ];
        foreach ($roster as $riotId => $team) {
            $this->players[$riotId] = new ObservedPlayer($riotId, explode('#', $riotId)[0], $team, 0, 0, 0);
        }
    }

    public static function aGame(): self
    {
        return new self();
    }

    public function inMode(string $gameMode): self
    {
        $this->gameMode = $gameMode;

        return $this;
    }

    public function at(float $gameTimeSeconds): self
    {
        $this->gameTimeSeconds = $gameTimeSeconds;

        return $this;
    }

    public function seenBy(string $activeRiotId): self
    {
        $this->activeRiotId = $activeRiotId;

        return $this;
    }

    public function withScore(string $riotId, int $kills, int $deaths, int $assists = 0): self
    {
        $player = $this->players[$riotId];
        $this->players[$riotId] = new ObservedPlayer($player->riotId, $player->gameName, $player->team, $kills, $deaths, $assists);

        return $this;
    }

    /** Remplace un joueur : la partie n'a alors plus les mêmes 10 joueurs. */
    public function replacing(string $riotId, string $newRiotId): self
    {
        $player = $this->players[$riotId];
        unset($this->players[$riotId]);
        $this->players[$newRiotId] = new ObservedPlayer($newRiotId, explode('#', $newRiotId)[0], $player->team, 0, 0, 0);

        return $this;
    }

    public function withEvents(ObservedEvent ...$events): self
    {
        $this->events = [...$this->events, ...$events];

        return $this;
    }

    public function build(): GameSnapshot
    {
        return new GameSnapshot($this->gameMode, $this->gameTimeSeconds, $this->activeRiotId, array_values($this->players), $this->events);
    }
}
