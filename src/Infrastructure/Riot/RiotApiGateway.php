<?php

namespace App\Infrastructure\Riot;

use App\Enum\RiotApiEnum;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RiotAPI\Base\Exceptions\GeneralException;
use RiotAPI\Base\Exceptions\RequestException;
use RiotAPI\Base\Exceptions\ServerException;
use RiotAPI\Base\Exceptions\ServerLimitException;
use RiotAPI\Base\Exceptions\SettingsException;
use RiotAPI\LeagueAPI\Objects\MatchDto;
use RiotAPI\LeagueAPI\Objects\SummonerDto;

#[WithMonologChannel('riot')]
readonly class RiotApiGateway
{
    // Clé Riot : 100 requêtes / 2 min. Un backfill (10 comptes x ~11 appels)
    // dépasse la fenêtre : plutôt que de sauter les matchs restants, on attend
    // la fin de la fenêtre et on retente.
    private const int RATE_LIMIT_MAX_ATTEMPTS = 3;
    private const int RATE_LIMIT_WAIT_SECONDS = 125;

   public function __construct(
       private RiotApiClient $riotApi,
       private LoggerInterface $logger = new NullLogger(),
   ) {}

    /**
     * Exécute un appel Riot en le chronométrant et en journalisant son issue.
     * Les exceptions sont relancées : le gateway observe, il ne décide pas —
     * c'est aux couches supérieures de choisir quoi faire d'un échec.
     * Seule exception : le rate limit Riot, retenté ici après attente (en CLI)
     * car aucune couche supérieure ne peut le résoudre autrement.
     */
    private function call(string $endpoint, callable $fn): mixed
    {
        for ($attempt = 1; true; $attempt++) {
            $start = hrtime(true);

            try {
                $result = $fn();

                $this->logger->info('riot.api.call', [
                    'endpoint' => $endpoint,
                    'duration_ms' => $this->elapsedMs($start),
                ]);

                return $result;
            } catch (ServerLimitException $e) {
                $this->logger->warning('riot.api.rate_limited', [
                    'endpoint' => $endpoint,
                    'duration_ms' => $this->elapsedMs($start),
                    'message' => $e->getMessage(),
                    'attempt' => $attempt,
                ]);

                // Attente bloquante réservée au CLI (cron, backfill) : une requête
                // web (/refresh) ne peut pas rester suspendue plusieurs minutes
                if (PHP_SAPI !== 'cli' || $attempt >= self::RATE_LIMIT_MAX_ATTEMPTS) {
                    throw $e;
                }

                sleep(self::RATE_LIMIT_WAIT_SECONDS);
            } catch (ServerException $e) {
                $this->logger->error('riot.api.server_error', [
                    'endpoint' => $endpoint,
                    'duration_ms' => $this->elapsedMs($start),
                    'message' => $e->getMessage(),
                ]);

                throw $e;
            } catch (RequestException $e) {
                $this->logger->warning('riot.api.request_error', [
                    'endpoint' => $endpoint,
                    'duration_ms' => $this->elapsedMs($start),
                    'message' => $e->getMessage(),
                ]);

                throw $e;
            }
        }
    }

    private function elapsedMs(int|float $start): float
    {
        return round((hrtime(true) - $start) / 1_000_000, 1);
    }

    /**
     * @throws ServerLimitException
     * @throws ServerException
     * @throws SettingsException
     * @throws RequestException
     * @throws GeneralException
     */
    public function getRankedsInformationsById($summonerId): ?array
    {
        return $this->call(
            'league-entries-for-summoner',
            fn () => $this->riotApi->riotApiInit()->getLeagueEntriesForSummoner($summonerId),
        );
   }

    /**
     * @param $summonerId
     * @return SummonerDto|null
     * @throws GeneralException
     * @throws RequestException
     * @throws ServerException
     * @throws ServerLimitException
     * @throws SettingsException
     */
   public function getSummonerAcountsDetails($summonerId): ?SummonerDto
   {
       return $this->call(
           'summoner-by-puuid',
           fn () => $this->riotApi->riotApiInit()->getSummonerByPUUID($summonerId),
       );
   }
    /**
     * @return array
     * @throws GeneralException
     * @throws RequestException
     * @throws ServerException
     * @throws ServerLimitException
     * @throws SettingsException
     * Obtiens la liste des matchs d'un compte Lol en utilisant son PUUID
     */
   public function getListIdMatchHistoryLol(string $puuid, ?int $startTime = null): array
   {
       return $this->call(
           'match-ids-by-puuid',
           fn () => $this->riotApi->riotApiInit()->getMatchIdsByPUUID($puuid,RiotApiEnum::QUEUE_TYPE_RANKED_SOLO->value,null,RiotApiEnum::START_INDEX->value,RiotApiEnum::MATCH_COUNT_RETRIEVE->value,$startTime),
       );
   }

    /**
     * @param string $matchId
     * @return MatchDto|null
     * @throws GeneralException
     * @throws RequestException
     * @throws ServerException
     * @throws ServerLimitException
     * @throws SettingsException
     */
   public function getDataMatchById(string $matchId): ?MatchDto
   {
       return $this->call(
           'get-match',
           fn () => $this->riotApi->riotApiInit()->getMatch($matchId),
       );
   }
}
