<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Contrôle : catalogue de règles paramétrables en base
 */
class CreateReglesControle extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 10],
            'intitule' => ['type' => 'VARCHAR', 'constraint' => 200],
            'description' => ['type' => 'TEXT', 'null' => true],
            'gravite' => ['type' => 'ENUM', 'constraint' => ['info', 'moyenne', 'elevee', 'bloquante'], 'default' => 'moyenne'],
            'poids' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 1],
            'parametres' => ['type' => 'JSON', 'null' => true],
            'message_erreur' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'action_corrective' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'source_legale' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('regles_controle', true);
    }

    public function down()
    {
        $this->forge->dropTable('regles_controle', true);
    }
}
