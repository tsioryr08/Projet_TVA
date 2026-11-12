<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Référentiel : sociétés déclarantes
 */
class CreateSocietes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'raison_sociale' => ['type' => 'VARCHAR', 'constraint' => 150],
            'nif' => ['type' => 'VARCHAR', 'constraint' => 30],
            'stat' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'adresse' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'activite' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'regime_fiscal' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nif');
        $this->forge->createTable('societes', true);
    }

    public function down()
    {
        $this->forge->dropTable('societes', true);
    }
}
