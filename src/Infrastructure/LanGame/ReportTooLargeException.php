<?php

namespace App\Infrastructure\LanGame;

/** Corps de relevé décompressé au-delà de la limite (contrat de l'agent, § 3 : 413). */
final class ReportTooLargeException extends \RuntimeException
{
}
