<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Documents : bordereaux et factures PDF
 */
class CreateDocuments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'societe_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'periode_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'tiers_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'type_document' => ['type' => 'ENUM', 'constraint' => ['facture', 'bordereau', 'bulletin', 'autre'], 'default' => 'bordereau'],
            'nom_fichier' => ['type' => 'VARCHAR', 'constraint' => 255],
            'chemin' => ['type' => 'VARCHAR', 'constraint' => 255],
            'hash_sha256' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'statut_extraction' => ['type' => 'ENUM', 'constraint' => ['a_extraire', 'extrait', 'erreur'], 'default' => 'a_extraire'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('societe_id');
        $this->forge->addKey('periode_id');
        $this->forge->addForeignKey('societe_id', 'societes', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('periode_id', 'periodes_fiscales', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('tiers_id', 'tiers', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('documents', true);
    }

    public function down()
    {
        $this->forge->dropTable('documents', true);
    }
}
