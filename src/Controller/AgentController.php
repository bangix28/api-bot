<?php

namespace App\Controller;

use App\Application\Agent\AgentPairingView;
use App\Application\Agent\ApproveDevice\ApproveDeviceCommand;
use App\Application\Agent\ApproveDevice\ApproveDeviceHandler;
use App\Application\Agent\ExchangeDeviceCode\DeviceNotReadyException;
use App\Application\Agent\ExchangeDeviceCode\ExchangeDeviceCodeHandler;
use App\Application\Agent\GetAgentSession\GetAgentSessionHandler;
use App\Application\Agent\RequestDeviceCode\RequestDeviceCodeHandler;
use App\Domain\Agent\AgentTokenExpiredException;
use App\Domain\Agent\InvalidDeviceCodeException;
use App\Domain\Agent\InvalidUserCodeException;
use App\Domain\Agent\NotRegisteredInAnyLanException;
use App\Domain\LanChallenge\LanSummary;
use App\Infrastructure\Security\AgentAccessTokenHandler;
use App\Infrastructure\Security\AgentAuthenticationResponder;
use App\Infrastructure\Security\AgentUser;
use App\Infrastructure\Security\PlayerUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Appairage et session de l'agent de LAN.
 * Contrat : docs/agent/contrat-releve.md (§ 1 et 2).
 */
class AgentController extends AbstractController
{
    #[Route('/api/agent/v1/device', name: 'api_agent_device', methods: ['POST'])]
    public function requestDeviceCode(
        RequestDeviceCodeHandler $handler,
        #[Autowire('%env(FRONT_AGENT_URL)%')] string $verificationUrl,
    ): JsonResponse {
        $deviceCode = $handler->handle();

        return new JsonResponse([
            'userCode' => $deviceCode->userCode,
            'deviceCode' => $deviceCode->deviceCode,
            'verificationUrl' => $verificationUrl,
            'expiresIn' => $deviceCode->expiresInSeconds,
            'interval' => $deviceCode->pollIntervalSeconds,
        ]);
    }

    #[Route('/api/me/agent-devices/approve', name: 'api_agent_device_approve', methods: ['POST'])]
    public function approveDevice(#[CurrentUser] PlayerUser $player, Request $request, ApproveDeviceHandler $handler): JsonResponse
    {
        $userCode = $request->getPayload()->all()['userCode'] ?? null;
        if (!is_string($userCode)) {
            return self::error(Response::HTTP_BAD_REQUEST, 'invalid_user_code', 'Le code affiché par l\'agent est obligatoire.');
        }

        try {
            return new JsonResponse(self::pairing($handler->handle(new ApproveDeviceCommand($player->discord, $userCode))));
        } catch (InvalidUserCodeException $exception) {
            return self::error(Response::HTTP_BAD_REQUEST, 'invalid_user_code', $exception->getMessage());
        } catch (NotRegisteredInAnyLanException $exception) {
            return self::error(Response::HTTP_CONFLICT, 'not_registered', $exception->getMessage());
        }
    }

    #[Route('/api/agent/v1/token', name: 'api_agent_token', methods: ['POST'])]
    public function exchangeDeviceCode(Request $request, ExchangeDeviceCodeHandler $handler): JsonResponse
    {
        $deviceCode = $request->getPayload()->all()['deviceCode'] ?? null;
        if (!is_string($deviceCode) || $deviceCode === '') {
            return self::error(Response::HTTP_BAD_REQUEST, 'invalid_grant', 'Le deviceCode est obligatoire.');
        }

        try {
            $issued = $handler->handle($deviceCode);
        } catch (DeviceNotReadyException $exception) {
            return self::error(Response::HTTP_BAD_REQUEST, $exception->status->value, $exception->getMessage());
        } catch (InvalidDeviceCodeException $exception) {
            return self::error(Response::HTTP_BAD_REQUEST, 'invalid_grant', $exception->getMessage());
        }

        return new JsonResponse([
            'token' => $issued->plainToken,
            'expiresAt' => $issued->token->expiresAt->format(\DateTimeInterface::ATOM),
            ...self::pairing($issued->pairing),
        ]);
    }

    #[Route('/api/agent/v1/session', name: 'api_agent_session', methods: ['GET'])]
    public function session(#[CurrentUser] AgentUser $agent, GetAgentSessionHandler $handler): JsonResponse
    {
        try {
            $session = $handler->handle($agent->token);
        } catch (AgentTokenExpiredException $exception) {
            return AgentAuthenticationResponder::unauthorized(AgentAccessTokenHandler::EXPIRED, $exception->getMessage());
        }

        return new JsonResponse([
            'player' => ['riotIds' => $session->riotIds],
            'lan' => [...self::lan($session->lan), 'status' => $session->lanInProgress ? 'in_progress' : 'upcoming'],
            'acceptedGameModes' => $session->acceptedGameModes,
            'reportIntervalSeconds' => $session->reportIntervalSeconds,
        ]);
    }

    /** @return array<string, mixed> */
    private static function pairing(AgentPairingView $pairing): array
    {
        return [
            'player' => ['riotIds' => $pairing->riotIds],
            'lan' => self::lan($pairing->lan),
        ];
    }

    /** @return array<string, mixed> */
    private static function lan(LanSummary $lan): array
    {
        return [
            'id' => $lan->lanId,
            'name' => $lan->name,
            'startsAt' => $lan->startsAt->format(\DateTimeInterface::ATOM),
            'endsAt' => $lan->endsAt->format(\DateTimeInterface::ATOM),
        ];
    }

    private static function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['code' => $code, 'message' => $message], $status);
    }
}
