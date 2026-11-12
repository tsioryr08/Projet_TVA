<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Comptabilité : en-têtes d'écritures
 */
class CreateEcritures extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'societe_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'periode_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'journal' => ['type' => 'VARCHAR', 'constraint' => 10],
            'numero_piece' => ['type' => 'VARCHAR', 'constraint' => 50],
            'date_ecriture' => ['type' => 'DATE', 'null' => false],
            'libelle' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'tiers_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'document_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('societe_id');
        $this->forge->addKey('periode_id');
        $this->forge->addKey('date_ecriture');
        $this->forge->addForeignKey('societe_id', 'societes', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('periode_id', 'periodes_fiscales', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('tiers_id', 'tiers', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('document_id', 'documents', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('ecritures', true);
    }

    public function down()
    {
        $this->forge->dropTable('ecritures', true);
    }
}
