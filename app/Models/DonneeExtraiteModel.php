<?php

namespace App\Models;

use CodeIgniter\Model;

class DonneeExtraiteModel extends Model
{
    protected $table            = 'donnees_extraites';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'document_id',
        'champ',
        'valeur',
        'confiance',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
}
