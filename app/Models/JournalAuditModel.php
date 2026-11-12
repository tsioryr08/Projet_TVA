<?php

namespace App\Models;

use CodeIgniter\Model;

class JournalAuditModel extends Model
{
    protected $table            = 'journal_audit';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'utilisateur_id',
        'action',
        'table_cible',
        'enregistrement_id',
        'donnees_avant',
        'donnees_apres',
        'hash_precedent',
        'hash_courant',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
}
