<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Gouvernance : utilisateurs
 */
class CreateUtilisateurs extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'role_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'nom' => ['type' => 'VARCHAR', 'constraint' => 100],
            'email' => ['type' => 'VARCHAR', 'constraint' => 150],
            'mot_de_passe' => ['type' => 'VARCHAR', 'constraint' => 255],
            'actif' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('utilisateurs', true);
    }

    public function down()
    {
        $this->forge->dropTable('utilisateurs', true);
    }
}
