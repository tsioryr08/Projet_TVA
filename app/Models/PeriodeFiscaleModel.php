<?php

namespace App\Models;

use CodeIgniter\Model;

class PeriodeFiscaleModel extends Model
{
    protected $table            = 'periodes_fiscales';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'societe_id',
        'annee',
        'mois',
        'date_debut',
        'date_fin',
        'statut',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
