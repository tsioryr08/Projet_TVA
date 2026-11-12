<?php

namespace App\Models;

use CodeIgniter\Model;

class ValidationModel extends Model
{
    protected $table            = 'validations';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'declaration_id',
        'utilisateur_id',
        'etape',
        'decision',
        'commentaire',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
}
