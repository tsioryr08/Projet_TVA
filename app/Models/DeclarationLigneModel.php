<?php

namespace App\Models;

use CodeIgniter\Model;

class DeclarationLigneModel extends Model
{
    protected $table            = 'declaration_lignes';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'declaration_id',
        'code_ligne',
        'libelle',
        'base',
        'taux',
        'montant',
    ];

    protected $useTimestamps = false;
}
