<?php

namespace App\Models;

use CodeIgniter\Model;

class ControleFiscalHistoriqueModel extends Model
{
    protected $table            = 'controles_fiscaux_historique';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'societe_id',
        'date_controle',
        'type_impot',
        'regle_code',
        'motif',
        'redressement_montant',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
