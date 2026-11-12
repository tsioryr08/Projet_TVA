<?php

namespace App\Models;

use CodeIgniter\Model;

class LigneEcritureModel extends Model
{
    protected $table            = 'lignes_ecriture';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'ecriture_id',
        'compte_id',
        'libelle',
        'debit',
        'credit',
        'code_tva',
    ];

    protected $useTimestamps = false;
}
