<?php

namespace App\Models;

use CodeIgniter\Model;

class TiersModel extends Model
{
    protected $table            = 'tiers';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'societe_id',
        'type',
        'raison_sociale',
        'nif',
        'stat',
        'nif_valide',
        'soumis_retenue',
        'non_resident',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
