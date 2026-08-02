<?php

namespace App\Infrastructure\EloSnapshot;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\EloSnapshot\RankedQueueType;
use App\Entity\SummonerEloDaily;
use Doctrine\ORM\QueryBuilder;

/**
 * La courbe /riot-account/{id}/elo-daily prédate les snapshots flex : sans
 * filtre explicite, elle mélangerait les deux files et casserait le front.
 * Par défaut on ne renvoie que la solo queue ; ?queueType=RANKED_FLEX_SR
 * permet d'opter pour la flex.
 */
class EloDailySoloDefaultExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($resourceClass !== SummonerEloDaily::class) {
            return;
        }

        if (!empty($context['filters']['queueType'])) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $parameterName = $queryNameGenerator->generateParameterName('queueType');

        $queryBuilder
            ->andWhere(sprintf('%s.queueType = :%s', $rootAlias, $parameterName))
            ->setParameter($parameterName, RankedQueueType::SOLO->value);
    }
}
