<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Déclarations : lignes de détail
 */
class CreateDeclarationLignes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'declaration_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'code_ligne' => ['type' => 'VARCHAR', 'constraint' => 30],
            'libelle' => ['type' => 'VARCHAR', 'constraint' => 200],
            'base' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'taux' => ['type' => 'DECIMAL', 'constraint' => '7,4', 'null' => true],
            'montant' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('declaration_id');
        $this->forge->addForeignKey('declaration_id', 'declarations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('declaration_lignes', true);
    }

    public function down()
    {
        $this->forge->dropTable('declaration_lignes', true);
    }
}
