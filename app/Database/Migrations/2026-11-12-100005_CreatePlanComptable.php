<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Référentiel : plan comptable (role_fiscal = tva_collectee, tva_deductible, irsa_retenu, retenue_source, cotisations)
 */
class CreatePlanComptable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'numero' => ['type' => 'VARCHAR', 'constraint' => 10],
            'libelle' => ['type' => 'VARCHAR', 'constraint' => 150],
            'classe' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true],
            'role_fiscal' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('numero');
        $this->forge->createTable('plan_comptable', true);
    }

    public function down()
    {
        $this->forge->dropTable('plan_comptable', true);
    }
}
