<?php

namespace App\Models;

use CodeIgniter\Model;

class BaremeIrsaModel extends Model
{
    protected $table            = 'baremes_irsa';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'tranche_numero',
        'borne_min',
        'borne_max',
        'taux',
        'date_effet',
        'date_fin',
        'source_legale',
        'verifie',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
