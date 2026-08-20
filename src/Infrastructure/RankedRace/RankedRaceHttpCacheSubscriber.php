<?php

namespace App\Infrastructure\RankedRace;

use App\Domain\RankedRace\RaceSnapshotRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Validation conditionnelle des classements de course.
 *
 * Le front interroge /ranked-race en boucle alors que la donnée ne change que
 * toutes les 30 minutes. L'ETag est dérivé de l'instant du dernier relevé, pas
 * d'un hachage de la réponse : un MAX(captured_at) indexé suffit alors à
 * répondre 304 SANS calculer le classement.
 *
 * Un hachage de contenu serait de toute façon inutilisable ici : la réponse
 * porte generatedAt et snapshotAgeSeconds, qui changent à chaque seconde.
 * Le client corrige l'âge affiché avec le temps écoulé depuis la réception.
 */
final readonly class RankedRaceHttpCacheSubscriber implements EventSubscriberInterface
{
    private const string ETAG_ATTRIBUTE = '_ranked_race_etag';

    /**
     * À incrémenter dès que les règles de calcul changent : sans cela, un
     * déploiement laisserait les clients sur d'anciennes valeurs, l'instant du
     * dernier relevé n'ayant pas bougé.
     */
    private const string ALGO_VERSION = 'v4';

    private const int MAX_AGE_SECONDS = 60;

    public function __construct(private RaceSnapshotRepositoryInterface $snapshots)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Après le routage, avant le contrôleur : c'est ce qui permet
            // d'économiser le calcul et pas seulement la bande passante.
            KernelEvents::REQUEST => ['onRequest', 4],
            KernelEvents::RESPONSE => ['onResponse', 0],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !$this->handles($request)) {
            return;
        }

        $etag = $this->etagFor($request);
        $request->attributes->set(self::ETAG_ATTRIBUTE, $etag);

        $notModified = new Response();
        $notModified->setEtag($etag);

        if ($notModified->isNotModified($request)) {
            $this->applyCacheHeaders($notModified, $etag);
            $event->setResponse($notModified);
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        $etag = $event->getRequest()->attributes->get(self::ETAG_ATTRIBUTE);

        if (!is_string($etag) || !$event->getResponse()->isSuccessful()) {
            return;
        }

        $this->applyCacheHeaders($event->getResponse(), $etag);
    }

    private function handles(Request $request): bool
    {
        return $request->isMethod(Request::METHOD_GET)
            && str_starts_with($request->getPathInfo(), '/api/ranked-race');
    }

    /**
     * Un seul MAX(captured_at), toutes files confondues : plus grossier qu'un
     * ETag par file, mais jamais périmé — un nouveau relevé, quelle que soit la
     * file, invalide toutes les vues.
     */
    private function etagFor(Request $request): string
    {
        $lastCapturedAt = $this->snapshots->lastCapturedAt();

        return sprintf(
            '%s-%s-%s',
            self::ALGO_VERSION,
            $lastCapturedAt?->getTimestamp() ?? 'vide',
            // La query string discrimine file et période ; triée, pour que
            // ?queue=solo&period=week et ?period=week&queue=solo aient le même ETag.
            substr(hash('xxh128', $request->getPathInfo() . '?' . $this->sortedQuery($request)), 0, 12),
        );
    }

    private function sortedQuery(Request $request): string
    {
        $params = $request->query->all();
        ksort($params);

        return http_build_query($params);
    }

    private function applyCacheHeaders(Response $response, string $etag): void
    {
        $response->setEtag($etag);
        // Jamais « public » : les routes ^/api/ranked sont derrière ROLE_API,
        // et Vary évite qu'un proxy serve la réponse d'un porteur à un autre.
        $response->setPrivate();
        $response->setMaxAge(self::MAX_AGE_SECONDS);
        $response->setVary(['Authorization'], false);
    }
}
