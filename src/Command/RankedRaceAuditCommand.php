<?php

namespace App\Command;

use App\Application\RankedRace\AuditSegments\AuditRaceSegmentsCommand;
use App\Application\RankedRace\AuditSegments\AuditRaceSegmentsHandler;
use App\Application\RankedRace\AuditSegments\PlayerAuditView;
use App\Domain\RankedRace\InvalidRankedRaceParameterException;
use App\Domain\RankedRace\TierCoefficient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ranked-race:audit',
    description: 'Décompose le score de course d\'un joueur, segment par segment',
)]
class RankedRaceAuditCommand extends Command
{
    public function __construct(
        private readonly AuditRaceSegmentsHandler $audit,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('queue', null, InputOption::VALUE_REQUIRED, 'File : solo ou flex', 'solo')
            ->addOption('period', null, InputOption::VALUE_REQUIRED, 'Période : week ou month', 'week')
            ->addOption('player', null, InputOption::VALUE_REQUIRED, 'riotId exact ; toute la file si omis')
            ->setHelp(
                "Lecture seule : aucune écriture en base, aucun appel à l'API Riot.\n"
                . "Relit le même port et le même domaine que /api/ranked-race, donc la somme\n"
                . "des segments doit égaler le weightedDelta du JSON pour le même joueur."
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $audits = $this->audit->handle(new AuditRaceSegmentsCommand(
                (string) $input->getOption('queue'),
                (string) $input->getOption('period'),
                $input->getOption('player') === null ? null : (string) $input->getOption('player'),
            ));
        } catch (InvalidRankedRaceParameterException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        if ($audits === []) {
            $io->warning('Aucun joueur trouvé pour ces paramètres.');

            return Command::SUCCESS;
        }

        foreach ($audits as $audit) {
            $this->renderPlayer($io, $audit);
        }

        $this->renderSummary($io, $audits);

        return Command::SUCCESS;
    }

    private function renderPlayer(SymfonyStyle $io, PlayerAuditView $audit): void
    {
        $io->section(sprintf('%s (%s)', $audit->summonerName, $audit->riotId));

        if ($audit->segments === []) {
            $io->text(sprintf(
                '<comment>Aucune partie jouée.</comment> Dernier rang connu : %s',
                $audit->finishRank,
            ));

            return;
        }

        $io->text(sprintf('Départ <info>%s</info>  →  arrivée <info>%s</info>', $audit->baselineRank, $audit->finishRank));
        $io->newLine();

        $rows = [];
        foreach ($audit->segments as $segment) {
            $rows[] = [
                $segment->toCapturedAt,
                $segment->fromRank,
                $segment->toRank,
                $segment->games,
                $segment->wins,
                self::signed($segment->scoreDelta),
                self::signed($segment->weightedDelta),
                $segment->tierCrossing === null ? '' : '<comment>' . $segment->tierCrossing . '</comment>',
            ];
        }

        $io->table(
            ['relevé', 'rang avant', 'rang après', 'games', 'wins', 'brut', 'pondéré', 'frontière'],
            $rows,
        );

        $io->text($this->totals($audit));
        $io->newLine();
    }

    private function totals(PlayerAuditView $audit): string
    {
        $ratio = $audit->effectiveCoefficient === null
            ? 'rapport n/a'
            : sprintf('rapport ×%s', self::decimal($audit->effectiveCoefficient));

        if ($this->isSuspect($audit)) {
            $ratio = sprintf(
                '<error> %s — hors de la plage [%s ; %s] </error>',
                $ratio,
                self::decimal(TierCoefficient::lowest()),
                self::decimal(TierCoefficient::highest()),
            );
        }

        return sprintf(
            'brut %s · pondéré %s · %s · %d partie(s) · hors course %s · %d frontière(s) franchie(s)',
            self::signed($audit->rawProgression),
            self::signed($audit->weightedProgression),
            $ratio,
            $audit->gamesPlayed,
            self::signed($audit->offRaceDelta),
            $audit->tierCrossings,
        );
    }

    private function renderSummary(SymfonyStyle $io, array $audits): void
    {
        $segments = 0;
        $crossings = 0;
        $suspects = [];

        foreach ($audits as $audit) {
            $segments += count($audit->segments);
            $crossings += $audit->tierCrossings;

            if ($this->isSuspect($audit)) {
                $suspects[] = sprintf('%s (×%s)', $audit->riotId, self::decimal($audit->effectiveCoefficient));
            }
        }

        $io->definitionList(
            ['Joueurs' => (string) count($audits)],
            ['Segments' => (string) $segments],
            ['Frontières franchies' => (string) $crossings],
        );

        if ($suspects === []) {
            $io->success('Aucun rapport pondéré/brut hors de la plage des coefficients.');

            return;
        }

        // Volontairement SUCCESS : l'audit constate, il ne juge pas. Un exit code
        // non nul en ferait une sonde, et cron l'alerterait à chaque anomalie
        // légitime (une démotion suffit à sortir de la plage).
        $io->warning(sprintf(
            "Rapport hors plage pour %d joueur(s) : %s\nÀ recouper avec la colonne « frontière » ci-dessus.",
            count($suspects),
            implode(', ', $suspects),
        ));
    }

    /**
     * Un rapport hors de la plage des coefficients trahit un delta pondéré qui
     * ne peut pas venir d'un seul tarif. Seule une progression brute POSITIVE
     * est testable : sur un brut négatif, un pondéré plus négatif encore est
     * normal (les coefficients sont > 1).
     */
    private function isSuspect(PlayerAuditView $audit): bool
    {
        if ($audit->effectiveCoefficient === null || $audit->rawProgression <= 0) {
            return false;
        }

        return $audit->effectiveCoefficient < TierCoefficient::lowest()
            || $audit->effectiveCoefficient > TierCoefficient::highest();
    }

    private static function signed(int|float $value): string
    {
        $formatted = self::decimal($value);

        return $value >= 0 ? '+' . $formatted : $formatted;
    }

    private static function decimal(int|float $value): string
    {
        return is_int($value) ? (string) $value : rtrim(rtrim(number_format($value, 3, ',', ' '), '0'), ',');
    }
}
