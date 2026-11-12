<?php

namespace App\Models;

use CodeIgniter\Model;

class DeclarationModel extends Model
{
    protected $table            = 'declarations';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'societe_id',
        'periode_id',
        'type',
        'statut',
        'montant_total',
        'date_echeance',
        'date_depot',
        'reference_virement',
        'cree_par',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
