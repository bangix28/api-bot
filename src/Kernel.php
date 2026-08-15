<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Fuseau de l'application, source unique.
     *
     * Une constante et non un paramètre du conteneur : boot() s'exécute contre
     * le conteneur présent sur le disque, qui pendant un déploiement est encore
     * celui de la release précédente — et en prod (debug=false) il n'est pas
     * revalidé. Y demander un paramètre récent fait échouer le cache:clear de
     * production, constaté au déploiement du 2026-08-15 :
     *
     *     In App_KernelProdContainer.php line 2100:
     *       You have requested a non-existent parameter "app.timezone".
     *
     * services.yaml relit la constante via !php/const : une seule valeur, deux
     * usages, et plus aucune lecture du conteneur au démarrage.
     */
    public const string TIMEZONE = 'Europe/Paris';

    public function boot(): void
    {
        // Avant parent::boot() : rien ne doit pouvoir lire une date avant que le
        // fuseau soit posé. Les instants sont stockés en heure civile (les
        // fenêtres de course sont des bornes civiles) ; si PHP tourne en UTC,
        // Doctrine réhydrate ces DATETIME sans fuseau en UTC et l'API annonce
        // « 15:08+00:00 » pour un relevé pris à 15:08 à Paris.
        date_default_timezone_set(self::TIMEZONE);

        parent::boot();
    }
}
