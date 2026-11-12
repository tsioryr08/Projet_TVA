<?php

namespace App\Models;

use CodeIgniter\Model;

class TauxFiscalModel extends Model
{
    protected $table            = 'taux_fiscaux';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'code',
        'libelle',
        'valeur',
        'unite',
        'date_effet',
        'date_fin',
        'source_legale',
        'verifie',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
