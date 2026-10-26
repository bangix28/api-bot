<?php

namespace App\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Réponses 401 de l'agent, au format du contrat (§ 2) : { code, message }.
 * Jeton absent, inconnu ou révoqué → invalid_token ; LAN terminée → token_expired.
 */
final class AgentAuthenticationResponder implements AuthenticationFailureHandlerInterface, AuthenticationEntryPointInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $exception->getMessageKey() === AgentAccessTokenHandler::EXPIRED
            ? self::unauthorized(AgentAccessTokenHandler::EXPIRED, 'La LAN de ce jeton est terminée.')
            : self::invalidToken();
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return self::invalidToken();
    }

    public static function unauthorized(string $code, string $message): JsonResponse
    {
        return new JsonResponse(['code' => $code, 'message' => $message], Response::HTTP_UNAUTHORIZED);
    }

    private static function invalidToken(): JsonResponse
    {
        return self::unauthorized('invalid_token', 'Jeton d\'agent absent, inconnu ou révoqué : recommence l\'appairage.');
    }
}
