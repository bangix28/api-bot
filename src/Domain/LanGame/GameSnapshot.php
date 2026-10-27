<?php

namespace App\Domain\LanGame;

use App\Domain\LanChallenge\LanQueue;
use App\Domain\LanGame\Event\GameEnd;
use App\Domain\LanGame\Event\ObservedEvent;

/**
 * Une partie vue par un agent à un instant donné : les 10 joueurs, leurs scores
 * et le journal d'événements depuis le début (cumulatif, ADR-0005).
 */
final readonly class GameSnapshot
{
    /**
     * @param ObservedPlayer[] $players
     * @param ObservedEvent[] $events dans l'ordre du journal
     */
    public function __construct(
        public string $gameMode,
        public float $gameTimeSeconds,
        /** Riot ID du joueur du poste qui a envoyé le relevé. */
        public string $activeRiotId,
        public array $players,
        public array $events,
        /** File lue par l'agent dans le client LoL ; null s'il n'a pas pu la lire. */
        public ?int $queueId = null,
    ) {
        if ($gameTimeSeconds < 0) {
            throw new \InvalidArgumentException('Le temps de jeu ne peut pas être négatif.');
        }

        if ($this->playerByRiotId($activeRiotId) === null) {
            throw new \InvalidArgumentException('Le joueur du poste doit figurer parmi les joueurs de la partie.');
        }
    }

    /** File de la partie ; null si le relevé ne permet pas de la connaître (LanQueue::observed). */
    public function queue(): ?LanQueue
    {
        return LanQueue::observed($this->queueId, $this->gameMode);
    }

    /**
     * Les 10 Riot ID de la partie, triés et sans casse : deux agents de la même
     * partie en tirent la même empreinte.
     */
    public function playersFingerprint(): string
    {
        $riotIds = array_map(static fn(ObservedPlayer $player) => mb_strtolower($player->riotId), $this->players);
        sort($riotIds);

        return implode('|', $riotIds);
    }

    public function playerByRiotId(string $riotId): ?ObservedPlayer
    {
        foreach ($this->players as $player) {
            if (strcasecmp($player->riotId, $riotId) === 0) {
                return $player;
            }
        }

        return null;
    }

    /** Joueur désigné par son pseudo dans un événement ; null pour un sbire, une tourelle… */
    public function playerByName(string $gameName): ?ObservedPlayer
    {
        foreach ($this->players as $player) {
            if ($player->gameName === $gameName) {
                return $player;
            }
        }

        return null;
    }

    public function gameEnd(): ?GameEnd
    {
        foreach ($this->events as $event) {
            if ($event instanceof GameEnd) {
                return $event;
            }
        }

        return null;
    }

    /**
     * @template T of ObservedEvent
     * @param class-string<T> $type
     * @return T[]
     */
    public function eventsOf(string $type): array
    {
        return array_values(array_filter($this->events, static fn(ObservedEvent $event) => $event instanceof $type));
    }
}
