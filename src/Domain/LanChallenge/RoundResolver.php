<?php

namespace App\Domain\LanChallenge;

/**
 * Règle une partie pour tous les joueurs qui y avaient engagé une carte.
 *
 * Bonus de manche : quand au moins deux joueurs ont engagé une carte sur la
 * même partie et que tous la réussissent, chacun gagne 50 % de points en plus.
 * Il récompense le fait de jouer ensemble, pas la performance d'un seul : un
 * joueur seul n'y a jamais droit, et un verdict inconnu le fait tomber.
 */
final readonly class RoundResolver
{
    public const int ROUND_BONUS_PERCENT = 50;

    public function __construct(private CardDealer $dealer)
    {
    }

    /**
     * @param PlayerRun[] $runs parcours de la LAN ; ceux qui n'ont rien engagé sur la partie sont ignorés
     * @return PlayerRun[] parcours réglés, chacun avec sa nouvelle offre
     */
    public function resolve(GameReport $report, array $runs): array
    {
        $engaged = array_values(array_filter($runs, static fn(PlayerRun $run) => $run->isLockedOn($report->gameId)));

        $verdicts = array_map(static fn(PlayerRun $run) => $run->evaluate($report), $engaged);

        $roundBonus = count($engaged) >= 2
            && array_all($verdicts, static fn(Verdict $v) => $v === Verdict::SUCCESS);

        return array_map(
            fn(PlayerRun $run, Verdict $verdict) => $run->settle($report->gameId, $verdict, $roundBonus, $this->dealer->dealOffer()),
            $engaged,
            $verdicts,
        );
    }
}
