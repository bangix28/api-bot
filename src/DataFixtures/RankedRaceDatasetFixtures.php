<?php

namespace App\DataFixtures;

use App\Domain\EloSnapshot\RankedQueueType;
use App\Domain\RiotAccount\RankedQueueEntity;
use App\Domain\RiotAccount\RankedRank;
use App\Domain\RiotAccount\RankedTier;
use App\Entity\RankedRaceEvent;
use App\Entity\RiotAccount;
use App\Entity\SummonerEloDaily;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu de données factice pour tester la Ranked Race (courses calendaires + événements).
 *
 * À charger avec --append : les données réelles sont conservées, tout ce que crée
 * cette fixture est marqué FIXTURE et supprimé/recréé à chaque chargement (rejouable).
 * Chaque persona exerce une règle métier précise (voir personas()).
 *
 * Toutes les dates sont relatives à aujourd'hui : le jeu reste pertinent quel que
 * soit le jour de chargement, et chaque persona a un snapshot du jour -> le cron
 * daily-elo les skippe (idempotence), aucun appel Riot sur les faux puuid.
 */
class RankedRaceDatasetFixtures extends Fixture
{
    private const string ACCOUNT_MARKER = 'FIXTURE-';
    private const string EVENT_MARKER = 'FIXTURE ';

    /** ~6 semaines d'historique : couvre la semaine courante, le mois courant et le mois précédent. */
    private const int HISTORY_DAYS = 41;

    public function load(ObjectManager $manager): void
    {
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException('Ces fixtures nécessitent l\'EntityManager de Doctrine ORM.');
        }

        $this->removePreviousDataset($manager);

        $today = new \DateTimeImmutable('today');

        foreach ($this->personas($today) as $persona) {
            $this->loadPersona($manager, $persona, $today);
        }

        $this->loadEvents($manager, $today);

        $manager->flush();
    }

    /**
     * Rejouabilité malgré --append : la fixture ne touche qu'à ses propres données,
     * identifiées par le marqueur FIXTURE, et les recrée de zéro.
     */
    private function removePreviousDataset(EntityManagerInterface $manager): void
    {
        $manager->createQuery(
            'DELETE App\Entity\SummonerEloDaily s
             WHERE IDENTITY(s.riotAccount) IN (
                 SELECT a.id FROM App\Entity\RiotAccount a WHERE a.riotId LIKE :marker
             )'
        )->setParameter('marker', self::ACCOUNT_MARKER . '%')->execute();

        $manager->createQuery('DELETE App\Entity\RiotAccount ra WHERE ra.riotId LIKE :marker')
            ->setParameter('marker', self::ACCOUNT_MARKER . '%')->execute();

        $manager->createQuery('DELETE App\Entity\RankedRaceEvent e WHERE e.name LIKE :marker')
            ->setParameter('marker', self::EVENT_MARKER . '%')->execute();
    }

    /**
     * Chaque persona : identité + une spec par file (solo/flex).
     * Spec : position de départ, compteurs wins/losses cumulés, longueur d'historique,
     * et un « plan » par jour -> [deltaLP, parties, victoires] ou null (trou : panne de cron).
     *
     * @return array<string, mixed>[]
     */
    private function personas(\DateTimeImmutable $today): array
    {
        $monday = $today->modify('monday this week');

        return [
            [
                // Progression A/B avec franchissement Gold -> Platine (le coef Gold
                // s'applique aux deltas réalisés en Gold, x1.4 ensuite).
                'slug' => 'grimpeur', 'name' => 'FIXTURE Grimpeur', 'logo' => '685', 'level' => 156,
                'solo' => $this->spec(RankedTier::GOLD, RankedRank::IV, 20, 210, 190, self::HISTORY_DAYS,
                    static fn(int $i) => [
                        [30, 26, -10, 34, 22, 28, 32][$i % 7],
                        [4, 4, 2, 3, 5, 4, 4][$i % 7],
                        [3, 3, 1, 2, 3, 3, 3][$i % 7],
                    ]),
                'flex' => $this->spec(RankedTier::GOLD, RankedRank::IV, 55, 60, 55, self::HISTORY_DAYS,
                    static fn(int $i) => [[-5, 8, 0, 6, -4, 0, 7][$i % 7], 1, [0, 1, 0, 1, 0, 0, 1][$i % 7]]),
            ],
            [
                // MÊME trajectoire solo que Grimpeur (départ + deltas identiques) mais
                // 6 parties/semaine au lieu de 26 : égalité parfaite de progression,
                // le tie-break « moins de parties » doit le classer devant.
                'slug' => 'efficace', 'name' => 'FIXTURE Efficace', 'logo' => '4021', 'level' => 89,
                'solo' => $this->spec(RankedTier::GOLD, RankedRank::IV, 20, 88, 30, self::HISTORY_DAYS,
                    static fn(int $i) => [
                        [30, 26, -10, 34, 22, 28, 32][$i % 7],
                        [1, 1, 1, 1, 1, 0, 1][$i % 7],
                        [1, 1, 0, 1, 1, 0, 1][$i % 7],
                    ]),
            ],
            [
                // Gros volume, winrate ~52%, faible delta : qualifié mais loin du podium.
                // Trou de série tous les 13 jours (panne de cron simulée).
                'slug' => 'grinder', 'name' => 'FIXTURE Grinder', 'logo' => '512', 'level' => 412,
                'solo' => $this->spec(RankedTier::SILVER, RankedRank::III, 50, 480, 460, self::HISTORY_DAYS,
                    static fn(int $i) => ($i % 13) === 5 ? null : [
                        [6, -4, 9, 2, -7, 12, 3][$i % 7],
                        8,
                        [4, 4, 5, 4, 3, 5, 4][$i % 7],
                    ]),
                'flex' => $this->spec(RankedTier::SILVER, RankedRank::II, 10, 140, 130, self::HISTORY_DAYS,
                    static fn(int $i) => [[5, 0, -3, 8, 2, 0, 4][$i % 7], 3, [2, 1, 1, 2, 2, 1, 2][$i % 7]]),
            ],
            [
                // Decay : perd des LP les jours sans partie (delta négatif assumé, coef x1.8).
                'slug' => 'decayer', 'name' => 'FIXTURE Decayer', 'logo' => '3543', 'level' => 267,
                'solo' => $this->spec(RankedTier::DIAMOND, RankedRank::II, 40, 320, 300, self::HISTORY_DAYS,
                    static fn(int $i) => [[-15, 0, 0], [-15, 0, 0], [45, 6, 4], [-15, 0, 0], [-15, 0, 0], [-10, 3, 1], [0, 0, 0]][$i % 7]),
            ],
            [
                // Apex : plancher Master + LP directs (RaceScore), coef x2.2.
                'slug' => 'apex', 'name' => 'FIXTURE ApexSmurf', 'logo' => '4568', 'level' => 999,
                'solo' => $this->spec(RankedTier::MASTER, RankedRank::UNRANKED, 150, 610, 540, self::HISTORY_DAYS,
                    static fn(int $i) => [
                        [12, -8, 21, 0, 15, -5, 7][$i % 7],
                        [3, 2, 4, 0, 3, 2, 2][$i % 7],
                        [2, 1, 3, 0, 2, 1, 1][$i % 7],
                    ]),
            ],
            [
                // 3 parties cette semaine (mardi + jeudi) : non-qualifié grisé « 3/5 ».
                // Les jours sans partie ont quand même un snapshot (le cron tourne toujours).
                'slug' => 'presque', 'name' => 'FIXTURE Presque', 'logo' => '23', 'level' => 67,
                'solo' => $this->spec(RankedTier::GOLD, RankedRank::I, 60, 150, 140, self::HISTORY_DAYS,
                    static function (int $i, \DateTimeImmutable $day) use ($monday) {
                        if ($day >= $monday) {
                            $offset = (int) $monday->diff($day)->days;

                            return match ($offset) {
                                1 => [15, 1, 1],   // mardi : 1 victoire
                                3 => [-3, 2, 1],   // jeudi : 1 victoire, 1 défaite
                                default => [0, 0, 0],
                            };
                        }

                        return [[12, -8, 15, 6, -10, 20, 4][$i % 7], 2, [1, 1, 2, 1, 0, 2, 1][$i % 7]];
                    }),
            ],
            [
                // Inscrit avant-hier : série courte, entre dans la course en cours de période.
                'slug' => 'nouveau', 'name' => 'FIXTURE Nouveau', 'logo' => '29', 'level' => 34,
                'solo' => $this->spec(RankedTier::EMERALD, RankedRank::III, 45, 95, 88, 2,
                    static fn(int $i) => [18, 2, 1]),
            ],
            [
                // Jamais classée en solo (aucune ligne solo = absente de la course solo),
                // mais grimpe en flex.
                'slug' => 'flexreine', 'name' => 'FIXTURE FlexReine', 'logo' => '777', 'level' => 203,
                'flex' => $this->spec(RankedTier::EMERALD, RankedRank::IV, 10, 220, 200, self::HISTORY_DAYS,
                    static fn(int $i) => [
                        [14, 8, -6, 18, 10, 2, 16][$i % 7],
                        [3, 3, 2, 4, 3, 1, 3][$i % 7],
                        [2, 2, 1, 3, 2, 1, 2][$i % 7],
                    ]),
            ],
        ];
    }

    /** @param array<string, mixed> $persona */
    private function loadPersona(EntityManagerInterface $manager, array $persona, \DateTimeImmutable $today): void
    {
        $account = new RiotAccount();
        $account->setRiotId(self::ACCOUNT_MARKER . $persona['slug'])
            ->setPuuid('fixture-puuid-' . $persona['slug'])
            ->setSummonerName($persona['name'])
            ->setLogoId($persona['logo'])
            ->setSummonerLevel($persona['level'])
            ->setLastUpdate(new \DateTime());
        $manager->persist($account);

        $soloFinal = isset($persona['solo'])
            ? $this->generateSeries($manager, $account, RankedQueueType::SOLO, $persona['solo'], $today)
            : null;
        $flexFinal = isset($persona['flex'])
            ? $this->generateSeries($manager, $account, RankedQueueType::FLEX, $persona['flex'], $today)
            : null;

        $this->denormalizeCurrentState($account, $soloFinal, $flexFinal);
    }

    /** @return array<string, mixed> */
    private function spec(RankedTier $tier, RankedRank $division, int $lp, int $wins, int $losses, int $days, callable $plan): array
    {
        return ['tier' => $tier, 'division' => $division, 'lp' => $lp, 'wins' => $wins, 'losses' => $losses, 'days' => $days, 'plan' => $plan];
    }

    /**
     * Déroule un plan quotidien en snapshots : jour 0 = position de départ, puis
     * chaque jour applique [deltaLP, parties, victoires] (null = pas de snapshot).
     * Le dernier jour est toujours aujourd'hui.
     *
     * @param array<string, mixed> $spec
     * @return RankedQueueEntity l'état final, pour dénormaliser RiotAccount
     */
    private function generateSeries(
        EntityManagerInterface $manager,
        RiotAccount $account,
        RankedQueueType $queue,
        array $spec,
        \DateTimeImmutable $today,
    ): RankedQueueEntity {
        $position = new LadderPosition($spec['tier'], $spec['division'], $spec['lp']);
        $wins = $spec['wins'];
        $losses = $spec['losses'];
        $ranked = $position->toRankedQueue($wins, $losses);

        for ($i = 0; $i <= $spec['days']; $i++) {
            $day = $today->modify(sprintf('-%d days', $spec['days'] - $i));

            if ($i > 0) {
                $plan = ($spec['plan'])($i, $day);

                if ($plan === null) {
                    continue; // trou dans la série : panne de cron simulée
                }

                [$lpDelta, $games, $dayWins] = $plan;
                $position->applyLpDelta($lpDelta);
                $wins += $dayWins;
                $losses += $games - $dayWins;
            }

            $ranked = $position->toRankedQueue($wins, $losses);

            $snapshot = new SummonerEloDaily();
            $snapshot->setRiotAccount($account)
                ->setDateScore(\DateTime::createFromImmutable($day))
                ->setQueueType($queue)
                // Même contrat que DoctrineEloSnapshotRepository::add() : le score
                // aplati alimente la courbe /elo-daily existante.
                ->setScore((string) $ranked->getScore())
                ->setTier($ranked->getTier()->value)
                ->setDivision($ranked->getDivision()->value)
                ->setLeaguePoints($ranked->getLeaguePoints())
                ->setWins($wins)
                ->setLosses($losses);
            $manager->persist($snapshot);
        }

        return $ranked;
    }

    /**
     * État courant dénormalisé sur RiotAccount : les personas apparaissent aussi
     * dans le classement « rang absolu » (/riot-account/ranked).
     */
    private function denormalizeCurrentState(RiotAccount $account, ?RankedQueueEntity $solo, ?RankedQueueEntity $flex): void
    {
        if ($solo !== null) {
            $account->setSummonerRankedSoloTier($solo->getTier()->value)
                ->setSummonerRankedSoloRank($solo->getDivision()->value)
                ->setSummonerRankedSoloLeaguePoints((string) $solo->getLeaguePoints())
                ->setSummonerRankedSoloWins($solo->getWins())
                ->setSummonerRankedSoloLosses((string) $solo->getLosses())
                ->setScore($solo->getScore());
        } else {
            // Représentation normalisée d'un compte non classé en solo
            // (cf. RiotApiServices::riotAccountFill, branche « non classé »).
            $account->setSummonerRankedSoloTier(RankedTier::UNRANKED->value)
                ->setSummonerRankedSoloRank(RankedRank::UNRANKED->value)
                ->setSummonerRankedSoloLeaguePoints('0')
                ->setSummonerRankedSoloWins(0)
                ->setSummonerRankedSoloLosses('0')
                ->setScore(0);
        }

        if ($flex !== null) {
            $account->setSummonerRankedFlexTier($flex->getTier()->value)
                ->setSummonerRankedFlexRank($flex->getDivision()->value)
                ->setSummonerRankedFlexLeaguePoints($flex->getLeaguePoints())
                ->setSummonerRankedFlexWins($flex->getWins())
                ->setSummonerRankedFlexLosses($flex->getLosses());
        }
    }

    private function loadEvents(EntityManagerInterface $manager, \DateTimeImmutable $today): void
    {
        $monday = $today->modify('monday this week');
        $sunday = $today->modify('sunday this week');

        $events = [
            // [nom, début, fin, file, seuil] — statuts attendus : finished / active / active / upcoming
            ['Rush du mois dernier', $today->modify('first day of last month'), $today->modify('last day of last month'), RankedQueueType::SOLO, 15],
            ['Sprint en cours', $monday, $sunday, RankedQueueType::SOLO, 3],
            ['Flex party', $monday, $sunday, RankedQueueType::FLEX, 2],
            ['Marathon à venir', $today->modify('first day of next month'), $today->modify('last day of next month'), RankedQueueType::FLEX, 15],
        ];

        foreach ($events as [$name, $start, $end, $queue, $minGames]) {
            $event = new RankedRaceEvent();
            $event->setName(self::EVENT_MARKER . $name)
                ->setStartDate($start)
                ->setEndDate($end)
                ->setQueueType($queue)
                ->setMinGamesToQualify($minGames);
            $manager->persist($event);
        }
    }
}
