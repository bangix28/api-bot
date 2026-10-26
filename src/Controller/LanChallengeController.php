<?php

namespace App\Controller;

use App\Application\LanChallenge\GetFeed\GetFeedHandler;
use App\Application\LanChallenge\GetMyRun\GetMyRunHandler;
use App\Application\LanChallenge\GetStandings\GetStandingsHandler;
use App\Application\LanChallenge\JoinChallenges\JoinChallengesCommand;
use App\Application\LanChallenge\JoinChallenges\JoinChallengesHandler;
use App\Application\LanChallenge\PickCard\PickCardCommand;
use App\Application\LanChallenge\PickCard\PickCardHandler;
use App\Application\LanChallenge\RerollCard\RerollCardCommand;
use App\Application\LanChallenge\RerollCard\RerollCardHandler;
use App\Domain\LanChallenge\InvalidOfferSlotException;
use App\Domain\LanChallenge\LanFinishedException;
use App\Domain\LanChallenge\LanNotFoundException;
use App\Domain\LanChallenge\NoOfferToChooseFromException;
use App\Domain\LanChallenge\NoRerollLeftException;
use App\Domain\LanChallenge\NotALanParticipantException;
use App\Domain\LanChallenge\PlayerRun;
use App\Domain\LanChallenge\PlayerRunNotFoundException;
use App\Domain\LanChallenge\RoundResolver;
use App\Domain\LanChallenge\SeveralAccountsInLanException;
use App\Infrastructure\Security\PlayerUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Défis de LAN : catalogue, classement et fil (lecture), parcours du joueur (actions).
 * Contrat pour le front : docs/front/defis-lan.md.
 */
class LanChallengeController extends AbstractController
{
    /** Erreurs métier → statut HTTP et code stable pour le front. */
    private const array ERRORS = [
        LanNotFoundException::class => [Response::HTTP_NOT_FOUND, 'lan_not_found'],
        NotALanParticipantException::class => [Response::HTTP_FORBIDDEN, 'not_a_participant'],
        SeveralAccountsInLanException::class => [Response::HTTP_CONFLICT, 'several_accounts_in_lan'],
        LanFinishedException::class => [Response::HTTP_CONFLICT, 'lan_finished'],
        PlayerRunNotFoundException::class => [Response::HTTP_CONFLICT, 'not_joined'],
        NoOfferToChooseFromException::class => [Response::HTTP_CONFLICT, 'no_offer'],
        NoRerollLeftException::class => [Response::HTTP_CONFLICT, 'no_reroll_left'],
        InvalidOfferSlotException::class => [Response::HTTP_BAD_REQUEST, 'invalid_slot'],
    ];

    #[Route('/api/lan-challenges/cards', name: 'api_lan_challenge_cards', methods: ['GET'])]
    public function cards(): JsonResponse
    {
        return new JsonResponse(LanChallengePresenter::catalogue(PlayerRun::INITIAL_REROLLS, RoundResolver::ROUND_BONUS_PERCENT));
    }

    #[Route('/api/lan-events/{lanId}/standings', name: 'api_lan_challenge_standings', requirements: ['lanId' => '\d+'], methods: ['GET'])]
    public function standings(int $lanId, GetStandingsHandler $handler): JsonResponse
    {
        return $this->respond(fn() => LanChallengePresenter::standings($handler->handle($lanId)));
    }

    #[Route('/api/lan-events/{lanId}/feed', name: 'api_lan_challenge_feed', requirements: ['lanId' => '\d+'], methods: ['GET'])]
    public function feed(int $lanId, Request $request, GetFeedHandler $handler): JsonResponse
    {
        $limit = $request->query->getInt('limit', GetFeedHandler::DEFAULT_LIMIT);

        return $this->respond(fn() => LanChallengePresenter::feed($handler->handle($lanId, $limit)));
    }

    #[Route('/api/lan-events/{lanId}/me', name: 'api_lan_challenge_my_run', requirements: ['lanId' => '\d+'], methods: ['GET'])]
    public function myRun(int $lanId, #[CurrentUser] PlayerUser $player, GetMyRunHandler $myRun): JsonResponse
    {
        return $this->respond(fn() => LanChallengePresenter::myRun($myRun->handle($lanId, $player->discord->userId)));
    }

    #[Route('/api/lan-events/{lanId}/me/join', name: 'api_lan_challenge_join', requirements: ['lanId' => '\d+'], methods: ['POST'])]
    public function join(int $lanId, #[CurrentUser] PlayerUser $player, JoinChallengesHandler $handler, GetMyRunHandler $myRun): JsonResponse
    {
        return $this->respond(function () use ($lanId, $player, $handler, $myRun) {
            $handler->handle(new JoinChallengesCommand($lanId, $player->discord->userId));

            return LanChallengePresenter::myRun($myRun->handle($lanId, $player->discord->userId));
        });
    }

    #[Route('/api/lan-events/{lanId}/me/pick', name: 'api_lan_challenge_pick', requirements: ['lanId' => '\d+'], methods: ['POST'])]
    public function pick(int $lanId, #[CurrentUser] PlayerUser $player, Request $request, PickCardHandler $handler, GetMyRunHandler $myRun): JsonResponse
    {
        $slot = self::slotFrom($request);
        if ($slot === null) {
            return self::error(Response::HTTP_BAD_REQUEST, 'invalid_slot', 'L\'emplacement de la carte (« slot », de 0 à 2) est obligatoire.');
        }

        return $this->respond(function () use ($lanId, $player, $slot, $handler, $myRun) {
            $handler->handle(new PickCardCommand($lanId, $player->discord->userId, $slot));

            return LanChallengePresenter::myRun($myRun->handle($lanId, $player->discord->userId));
        });
    }

    #[Route('/api/lan-events/{lanId}/me/reroll', name: 'api_lan_challenge_reroll', requirements: ['lanId' => '\d+'], methods: ['POST'])]
    public function reroll(int $lanId, #[CurrentUser] PlayerUser $player, Request $request, RerollCardHandler $handler, GetMyRunHandler $myRun): JsonResponse
    {
        $slot = self::slotFrom($request);
        if ($slot === null) {
            return self::error(Response::HTTP_BAD_REQUEST, 'invalid_slot', 'L\'emplacement de la carte (« slot », de 0 à 2) est obligatoire.');
        }

        return $this->respond(function () use ($lanId, $player, $slot, $handler, $myRun) {
            $handler->handle(new RerollCardCommand($lanId, $player->discord->userId, $slot));

            return LanChallengePresenter::myRun($myRun->handle($lanId, $player->discord->userId));
        });
    }

    /** @param callable(): array<string, mixed> $action */
    private function respond(callable $action): JsonResponse
    {
        try {
            return new JsonResponse($action());
        } catch (\DomainException | \InvalidArgumentException $exception) {
            [$status, $code] = self::ERRORS[$exception::class] ?? throw $exception;

            return self::error($status, $code, $exception->getMessage());
        }
    }

    private static function slotFrom(Request $request): ?int
    {
        $slot = $request->getPayload()->all()['slot'] ?? null;

        return is_int($slot) ? $slot : null;
    }

    private static function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['code' => $code, 'message' => $message], $status);
    }
}
