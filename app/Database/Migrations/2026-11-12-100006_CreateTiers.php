<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Référentiel : clients, fournisseurs, prestataires
 */
class CreateTiers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'societe_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'type' => ['type' => 'ENUM', 'constraint' => ['client', 'fournisseur', 'prestataire', 'autre'], 'default' => 'autre'],
            'raison_sociale' => ['type' => 'VARCHAR', 'constraint' => 150],
            'nif' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'stat' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'nif_valide' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'soumis_retenue' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'non_resident' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('societe_id');
        $this->forge->addKey('nif');
        $this->forge->addForeignKey('societe_id', 'societes', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('tiers', true);
    }

    public function down()
    {
        $this->forge->dropTable('tiers', true);
    }
}
