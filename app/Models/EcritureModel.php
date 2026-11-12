<?php

namespace App\Models;

use CodeIgniter\Model;

class EcritureModel extends Model
{
    protected $table            = 'ecritures';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'societe_id',
        'periode_id',
        'journal',
        'numero_piece',
        'date_ecriture',
        'libelle',
        'tiers_id',
        'document_id',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
