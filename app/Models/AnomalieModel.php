<?php

namespace App\Models;

use CodeIgniter\Model;

class AnomalieModel extends Model
{
    protected $table            = 'anomalies';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'societe_id',
        'periode_id',
        'regle_id',
        'severite',
        'montant_concerne',
        'reference_type',
        'reference_id',
        'message',
        'action_corrective',
        'statut',
        'justification',
        'detectee_le',
        'traitee_par',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
