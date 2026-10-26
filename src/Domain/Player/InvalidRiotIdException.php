<?php

namespace App\Domain\Player;

/** Riot ID mal formé (attendu « Pseudo#TAG »). */
class InvalidRiotIdException extends \InvalidArgumentException
{
}
