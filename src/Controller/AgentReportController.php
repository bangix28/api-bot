<?php

namespace App\Controller;

use App\Application\LanGame\ReceiveReport\ReceiveReportHandler;
use App\Domain\Agent\AgentTokenExpiredException;
use App\Domain\LanChallenge\LanNotFoundException;
use App\Domain\LanGame\InvalidReportException;
use App\Domain\LanGame\NotYourAccountException;
use App\Domain\LanGame\ReportTooFrequentException;
use App\Infrastructure\LanGame\AgentReportRequestParser;
use App\Infrastructure\LanGame\ReportTooLargeException;
use App\Infrastructure\Security\AgentAccessTokenHandler;
use App\Infrastructure\Security\AgentAuthenticationResponder;
use App\Infrastructure\Security\AgentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Relevés des agents de LAN (contrat de l'agent, § 3). */
final class AgentReportController extends AbstractController
{
    #[Route('/api/agent/v1/reports', name: 'api_agent_reports', methods: ['POST'])]
    public function receive(#[CurrentUser] AgentUser $agent, Request $request, ReceiveReportHandler $handler): JsonResponse
    {
        try {
            $command = AgentReportRequestParser::parse($agent->token, $request->getContent(), $request->headers->get('Content-Encoding'));
            $ignored = $handler->handle($command);
        } catch (InvalidReportException $exception) {
            return self::error(Response::HTTP_BAD_REQUEST, 'invalid_report', $exception->getMessage());
        } catch (ReportTooLargeException $exception) {
            return self::error(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'report_too_large', $exception->getMessage());
        } catch (AgentTokenExpiredException $exception) {
            return AgentAuthenticationResponder::unauthorized(AgentAccessTokenHandler::EXPIRED, $exception->getMessage());
        } catch (LanNotFoundException) {
            // La LAN du jeton a été supprimée : le jeton ne vaut plus rien.
            return AgentAuthenticationResponder::invalidToken();
        } catch (NotYourAccountException $exception) {
            return self::error(Response::HTTP_FORBIDDEN, 'not_your_account', $exception->getMessage());
        } catch (ReportTooFrequentException $exception) {
            $response = self::error(Response::HTTP_TOO_MANY_REQUESTS, 'too_many_reports', $exception->getMessage());
            $response->headers->set('Retry-After', (string) ReceiveReportHandler::MIN_SECONDS_BETWEEN_REPORTS);

            return $response;
        }

        return $ignored === null
            ? new JsonResponse(['status' => 'accepted'], Response::HTTP_ACCEPTED)
            : new JsonResponse(['status' => 'ignored', 'reason' => $ignored->value]);
    }

    private static function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['code' => $code, 'message' => $message], $status);
    }
}
