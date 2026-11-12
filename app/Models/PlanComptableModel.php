<?php

namespace App\Models;

use CodeIgniter\Model;

class PlanComptableModel extends Model
{
    protected $table            = 'plan_comptable';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'numero',
        'libelle',
        'classe',
        'role_fiscal',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
