<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Gouvernance : validations humaines du workflow
 */
class CreateValidations extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'declaration_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'utilisateur_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'etape' => ['type' => 'ENUM', 'constraint' => ['revue_comptable', 'validation_responsable'], 'default' => 'revue_comptable'],
            'decision' => ['type' => 'ENUM', 'constraint' => ['valide', 'rejete', 'a_corriger'], 'default' => 'valide'],
            'commentaire' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('declaration_id');
        $this->forge->addForeignKey('declaration_id', 'declarations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('utilisateur_id', 'utilisateurs', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('validations', true);
    }

    public function down()
    {
        $this->forge->dropTable('validations', true);
    }
}
