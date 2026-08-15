<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();

        // Les instants sont stockés en heure civile (cf. SystemClock, qui écrit
        // dans app.timezone) parce que les fenêtres de course sont des bornes
        // civiles. Le fuseau de PHP doit donc être le même : sinon Doctrine
        // réhydrate ces DATETIME sans fuseau en UTC, et l'API annonce
        // « 15:08+00:00 » pour un relevé pris à 15:08 à Paris — deux heures
        // d'écart, qui faisaient tomber l'âge des données à zéro.
        date_default_timezone_set((string) $this->getContainer()->getParameter('app.timezone'));
    }
}
