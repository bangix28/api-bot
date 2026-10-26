<?php

namespace App\Domain\LanChallenge;

/**
 * Joue une carte qui vise un joueur. Effet immédiat, sans match :
 *
 * - Pickpocket : la cible perd une relance, le saboteur la gagne ;
 * - Malédiction : le prochain défi réussi de la cible est réduit de moitié.
 *
 * Dans les deux cas le saboteur reçoit aussitôt une offre neuve : saboter est
 * un bonus en plus de sa manche, pas à sa place.
 */
final readonly class SabotageService
{
    public function __construct(private CardDealer $dealer)
    {
    }

    public function sabotage(PlayerRun $saboteur, int $slot, PlayerRun $target): SabotageOutcome
    {
        $card = $saboteur->cardInOffer($slot);

        if (!$card->targetsPlayer()) {
            throw new SabotageTargetNotAllowedException(sprintf('La carte « %s » ne vise pas de joueur.', $card->label()));
        }

        if ($target->puuid === $saboteur->puuid) {
            throw new InvalidSabotageTargetException('On ne se sabote pas soi-même.');
        }

        if ($target->lanId !== $saboteur->lanId) {
            throw new InvalidSabotageTargetException('La cible ne joue pas la même LAN.');
        }

        [$sabotagedTarget, $rerollsGained] = match ($card) {
            Challenge::PICKPOCKET => [$target->loseReroll(), 1],
            Challenge::CURSE => [$target->curse(), 0],
        };

        return new SabotageOutcome(
            $saboteur->afterSabotage($slot, $this->dealer->dealOffer(), $rerollsGained),
            $sabotagedTarget,
            new Sabotage($card, $saboteur->puuid, $target->puuid),
        );
    }
}
