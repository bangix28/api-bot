<?php

namespace App\Domain\LanGame;

/** Relevés intermédiaires envoyés plus vite que la cadence autorisée. */
class ReportTooFrequentException extends \DomainException
{
}
