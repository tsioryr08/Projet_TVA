<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Salaires : employés
 */
class CreateEmployes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'societe_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'matricule' => ['type' => 'VARCHAR', 'constraint' => 30],
            'nom' => ['type' => 'VARCHAR', 'constraint' => 100],
            'prenom' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'numero_cnaps' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'nb_charges' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true],
            'date_embauche' => ['type' => 'DATE', 'null' => true],
            'actif' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['societe_id', 'matricule']);
        $this->forge->addForeignKey('societe_id', 'societes', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('employes', true);
    }

    public function down()
    {
        $this->forge->dropTable('employes', true);
    }
}
