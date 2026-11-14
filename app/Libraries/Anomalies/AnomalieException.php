<?php

namespace App\Libraries\Anomalies;

use RuntimeException;

/** Refus d'une action sur une anomalie : le message est destiné à être affiché à l'utilisateur. */
class AnomalieException extends RuntimeException
{
}
