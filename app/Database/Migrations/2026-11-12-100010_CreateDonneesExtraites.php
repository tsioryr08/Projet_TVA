<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Documents : champs lus dans le PDF (OCR et IA)
 */
class CreateDonneesExtraites extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'document_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'champ' => ['type' => 'VARCHAR', 'constraint' => 50],
            'valeur' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'confiance' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('document_id');
        $this->forge->addForeignKey('document_id', 'documents', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('donnees_extraites', true);
    }

    public function down()
    {
        $this->forge->dropTable('donnees_extraites', true);
    }
}
