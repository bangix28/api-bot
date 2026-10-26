<?php

namespace App\Application\LanGame\ReceiveReport;

use App\Application\LanChallenge\ResolveGame\ResolveGameCommand;
use App\Application\LanChallenge\ResolveGame\ResolveGameHandler;
use App\Application\LanChallenge\StartGame\StartGameCommand;
use App\Application\LanChallenge\StartGame\StartGameHandler;
use App\Domain\Agent\AgentSettings;
use App\Domain\Agent\AgentTokenExpiredException;
use App\Domain\LanChallenge\LanNotFoundException;
use App\Domain\LanChallenge\LanSummary;
use App\Domain\LanChallenge\LanSummaryReaderInterface;
use App\Domain\LanGame\AgentReportLogInterface;
use App\Domain\LanGame\GameSnapshot;
use App\Domain\LanGame\GameStatsCalculator;
use App\Domain\LanGame\LanGame;
use App\Domain\LanGame\LanGameRepositoryInterface;
use App\Domain\LanGame\LanGameStatus;
use App\Domain\LanGame\NotYourAccountException;
use App\Domain\LanGame\ReportTooFrequentException;
use App\Domain\Player\TrackedRiotAccount;
use App\Domain\Player\TrackedRiotAccountRepositoryInterface;
use App\Domain\Shared\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Reçoit un relevé d'agent et fait avancer la partie qu'il décrit (ADR-0005,
 * décision 2 ; contrat de l'agent, § 3 et 4) :
 *
 * - le premier relevé d'une partie la déclare commencée et verrouille les
 *   cartes des participants présents — sauf s'il arrive après 90 s de jeu ;
 * - le relevé final calcule les statistiques et règle les cartes ;
 * - un second relevé final est recoupé avec le premier : un désaccord est
 *   journalisé, le règlement n'est pas rejoué.
 */
final readonly class ReceiveReportHandler
{
    /** Écart minimal entre deux relevés intermédiaires d'un même agent. */
    public const int MIN_SECONDS_BETWEEN_REPORTS = 5;

    public function __construct(
        private LanSummaryReaderInterface $lans,
        private TrackedRiotAccountRepositoryInterface $accounts,
        private LanGameRepositoryInterface $games,
        private AgentReportLogInterface $reportLog,
        private StartGameHandler $startGame,
        private ResolveGameHandler $resolveGame,
        private ClockInterface $clock,
        private LoggerInterface $refreshLogger = new NullLogger(),
    ) {
    }

    public function handle(ReceiveReportCommand $command): ?IgnoredReason
    {
        $now = $this->clock->now();
        $token = $command->token;
        $lan = $this->lans->find($token->lanId)
            ?? throw new LanNotFoundException('La LAN de ce jeton n\'existe plus.');

        $this->guardCadence($command, $now);

        // Après la fin de la LAN, seul le relevé final d'une partie en cours
        // passe encore (heure de grâce, vérifiée à l'authentification).
        if (!$command->final && $token->isExpiredAt($now)) {
            throw new AgentTokenExpiredException('La LAN de ce jeton est terminée.');
        }

        $this->guardOwnership($command);

        $ignored = $this->ignoredReason($command, $lan, $now);
        if ($ignored !== null) {
            return $ignored;
        }

        $participants = self::participantsIn($command->snapshot, $lan);
        $game = $this->games->findBySession($token->digest, $command->sessionId)
            ?? $this->findMatchingGame($lan, $command->snapshot, $now)
            ?? $this->openGame($lan, $command->snapshot, $participants, $now);

        if ($game === null) {
            return IgnoredReason::LAN_FINISHED;
        }

        $this->reportLog->record(
            $token->digest, $command->sessionId, $command->sequence, $game->gameId, $now,
            $command->final ? $command->rawPayload : null,
        );

        if ($game->status === LanGameStatus::JOINED_TOO_LATE) {
            return IgnoredReason::JOINED_TOO_LATE;
        }

        $game = $game->withReportAt($now);
        if ($command->final) {
            $game = $this->conclude($game, $command->snapshot, $participants);
        }
        $this->games->save($game);

        return null;
    }

    private function guardCadence(ReceiveReportCommand $command, \DateTimeImmutable $now): void
    {
        // Le relevé final n'est jamais freiné : c'est le seul qui compte pour les cartes.
        if ($command->final) {
            return;
        }

        $lastReceivedAt = $this->reportLog->lastReceivedAt($command->token->digest);
        if ($lastReceivedAt !== null && $now->getTimestamp() - $lastReceivedAt->getTimestamp() < self::MIN_SECONDS_BETWEEN_REPORTS) {
            throw new ReportTooFrequentException(sprintf('Un relevé toutes les %d secondes au plus.', self::MIN_SECONDS_BETWEEN_REPORTS));
        }
    }

    /** Un agent n'envoie que sa propre partie : le joueur du poste doit être l'un des comptes du joueur. */
    private function guardOwnership(ReceiveReportCommand $command): void
    {
        $ownedRiotIds = array_map(
            static fn(TrackedRiotAccount $account) => mb_strtolower($account->riotId),
            $this->accounts->findOwnedBy($command->token->player->userId),
        );

        if (!in_array(mb_strtolower($command->snapshot->activeRiotId), $ownedRiotIds, true)) {
            throw new NotYourAccountException('Le joueur du poste n\'est pas un de tes comptes : un agent n\'envoie que sa propre partie.');
        }
    }

    private function ignoredReason(ReceiveReportCommand $command, LanSummary $lan, \DateTimeImmutable $now): ?IgnoredReason
    {
        return match (true) {
            $now < $lan->startsAt => IgnoredReason::LAN_NOT_STARTED,
            !in_array($command->snapshot->gameMode, AgentSettings::ACCEPTED_GAME_MODES, true) => IgnoredReason::GAME_MODE_NOT_ACCEPTED,
            self::participantsIn($command->snapshot, $lan) === [] => IgnoredReason::NO_PARTICIPANT_IN_GAME,
            $this->isStale($command) => IgnoredReason::STALE_SEQUENCE,
            default => null,
        };
    }

    private function isStale(ReceiveReportCommand $command): bool
    {
        $lastSequence = $this->reportLog->lastSequence($command->token->digest, $command->sessionId);

        return $lastSequence !== null && $command->sequence <= $lastSequence;
    }

    private function findMatchingGame(LanSummary $lan, GameSnapshot $snapshot, \DateTimeImmutable $now): ?LanGame
    {
        $candidates = $this->games->findCandidates($lan->lanId, $snapshot->gameMode, $snapshot->playersFingerprint());

        return array_find($candidates, static fn(LanGame $candidate) => $candidate->matches($snapshot, $now));
    }

    /**
     * Premier relevé d'une partie : elle commence. Une partie commencée après la
     * fin de la LAN ne compte pas ; une partie rejointe trop tard n'engage aucune carte.
     *
     * @param array<string, string> $participants
     */
    private function openGame(LanSummary $lan, GameSnapshot $snapshot, array $participants, \DateTimeImmutable $now): ?LanGame
    {
        if (LanGame::estimatedStart($snapshot, $now) > $lan->endsAt) {
            return null;
        }

        $game = LanGame::open(LanGame::idFor($lan->lanId, $snapshot, $now), $lan->lanId, $snapshot, $now);
        $this->games->save($game);

        if ($game->engagesCards()) {
            $this->startGame->handle(new StartGameCommand($lan->lanId, $game->gameId, array_values($participants)));
        }

        return $game;
    }

    /** @param array<string, string> $participants */
    private function conclude(LanGame $game, GameSnapshot $finalSnapshot, array $participants): LanGame
    {
        if ($game->status === LanGameStatus::IN_PROGRESS) {
            $this->resolveGame->handle(new ResolveGameCommand(
                $game->lanId,
                GameStatsCalculator::report($game->gameId, $finalSnapshot, $participants),
            ));

            return $game->settle($finalSnapshot);
        }

        if ($game->status === LanGameStatus::SETTLED && !$game->agreesWith($finalSnapshot)) {
            $this->refreshLogger->warning('Relevés finaux contradictoires : le premier reçu fait foi', ['gameId' => $game->gameId]);
        }

        return $game;
    }

    /** @return array<string, string> puuid des participants de la LAN présents, indexés par Riot ID en minuscules */
    private static function participantsIn(GameSnapshot $snapshot, LanSummary $lan): array
    {
        $participants = [];
        foreach ($lan->participants as $puuid => $riotId) {
            if ($snapshot->playerByRiotId($riotId) !== null) {
                $participants[mb_strtolower($riotId)] = $puuid;
            }
        }

        return $participants;
    }
}
