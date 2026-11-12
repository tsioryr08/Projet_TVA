<?php

namespace App\Models;

use CodeIgniter\Model;

class SocieteModel extends Model
{
    protected $table            = 'societes';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'raison_sociale',
        'nif',
        'stat',
        'adresse',
        'activite',
        'regime_fiscal',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
