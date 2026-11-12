<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Comptabilité : lignes débit et crédit
 */
class CreateLignesEcriture extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'ecriture_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'compte_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'libelle' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'debit' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'credit' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'code_tva' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('ecriture_id');
        $this->forge->addKey('compte_id');
        $this->forge->addForeignKey('ecriture_id', 'ecritures', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('compte_id', 'plan_comptable', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('lignes_ecriture', true);
    }

    public function down()
    {
        $this->forge->dropTable('lignes_ecriture', true);
    }
}
