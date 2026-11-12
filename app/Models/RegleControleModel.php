<?php

namespace App\Models;

use CodeIgniter\Model;

class RegleControleModel extends Model
{
    protected $table            = 'regles_controle';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'code',
        'intitule',
        'description',
        'gravite',
        'poids',
        'parametres',
        'message_erreur',
        'action_corrective',
        'source_legale',
        'active',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
