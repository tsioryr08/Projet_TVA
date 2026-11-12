<?php

namespace App\Models;

use CodeIgniter\Model;

class AnomaliePreuveModel extends Model
{
    protected $table            = 'anomalie_preuves';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'anomalie_id',
        'table_source',
        'enregistrement_id',
        'libelle',
        'montant',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
}
