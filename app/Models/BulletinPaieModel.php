<?php

namespace App\Models;

use CodeIgniter\Model;

class BulletinPaieModel extends Model
{
    protected $table            = 'bulletins_paie';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'employe_id',
        'periode_id',
        'document_id',
        'salaire_brut',
        'cotisation_cnaps_salarie',
        'cotisation_ostie_salarie',
        'base_imposable',
        'reduction_charges',
        'irsa_retenu',
        'net_a_payer',
        'cotisation_cnaps_patronale',
        'cotisation_ostie_patronale',
        'fmfp_patronale',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
