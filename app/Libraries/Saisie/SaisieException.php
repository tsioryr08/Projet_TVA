<?php

namespace App\Libraries\Saisie;

use RuntimeException;

/** Refus d'une saisie (donnée invalide, fichier refusé) : le message est destiné à être affiché à l'utilisateur. */
class SaisieException extends RuntimeException
{
}
