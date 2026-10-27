<?php

namespace App\Domain\LanChallenge;

/**
 * Files où les défis de LAN comptent. Chaque LAN choisit les siennes en
 * administration. La valeur est l'identifiant de file de Riot (queueId), lu par
 * l'agent dans le client LoL : la Live Client Data API ne donne que le mode,
 * et CLASSIC vaut aussi bien pour la SoloQ, la Flex, une normale ou une partie
 * personnalisée.
 */
enum LanQueue: int
{
    case ARAM_MAYHEM = 2400;
    case RANKED_SOLO = 420;
    case RANKED_FLEX = 440;

    public function label(): string
    {
        return match ($this) {
            self::ARAM_MAYHEM => 'ARAM du chaos',
            self::RANKED_SOLO => 'Classée solo/duo',
            self::RANKED_FLEX => 'Classée flexible',
        };
    }

    /** gameData.gameMode de la Live Client Data API pour cette file (KIWI vérifié sur une vraie partie, ADR-0005). */
    public function gameMode(): string
    {
        return match ($this) {
            self::ARAM_MAYHEM => 'KIWI',
            self::RANKED_SOLO, self::RANKED_FLEX => 'CLASSIC',
        };
    }

    /** Partie sur la Faille de l'invocateur : les cartes y ont leurs propres seuils. */
    public function playsOnRift(): bool
    {
        return $this->gameMode() === 'CLASSIC';
    }

    /**
     * File d'une partie observée par un agent. Sans queueId (client LoL
     * illisible), le mode suffit s'il ne désigne qu'une file : KIWI, oui ;
     * CLASSIC, non. Un queueId incohérent avec le mode ne désigne aucune file.
     */
    public static function observed(?int $queueId, string $gameMode): ?self
    {
        if ($queueId !== null) {
            $queue = self::tryFrom($queueId);

            return $queue?->gameMode() === $gameMode ? $queue : null;
        }

        $candidates = array_values(array_filter(self::cases(), static fn(self $queue) => $queue->gameMode() === $gameMode));

        return count($candidates) === 1 ? $candidates[0] : null;
    }
}
