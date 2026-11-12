<?php

namespace App\Libraries\Workflow;

use RuntimeException;

/** Refus d'une action du workflow : le message est destiné à être affiché à l'utilisateur. */
class WorkflowException extends RuntimeException
{
}
