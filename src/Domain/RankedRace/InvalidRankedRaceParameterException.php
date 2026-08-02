<?php

namespace App\Domain\RankedRace;

/** Paramètre de course invalide (queue ou period) — mappée en 400 par API Platform. */
class InvalidRankedRaceParameterException extends \InvalidArgumentException
{
}
