<?php

namespace App\Models;

use CodeIgniter\Model;

class ScoreRisqueModel extends Model
{
    protected $table            = 'scores_risque';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'societe_id',
        'periode_id',
        'score',
        'facteur_historique',
        'niveau',
        'detail',
        'calcule_le',
    ];

    protected $useTimestamps = false;
}
