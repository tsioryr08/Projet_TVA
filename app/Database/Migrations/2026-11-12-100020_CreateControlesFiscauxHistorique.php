<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Historique : contrôles fiscaux passés
 */
class CreateControlesFiscauxHistorique extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'societe_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'date_controle' => ['type' => 'DATE', 'null' => false],
            'type_impot' => ['type' => 'VARCHAR', 'constraint' => 20],
            'regle_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'motif' => ['type' => 'TEXT', 'null' => true],
            'redressement_montant' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('societe_id');
        $this->forge->addForeignKey('societe_id', 'societes', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('controles_fiscaux_historique', true);
    }

    public function down()
    {
        $this->forge->dropTable('controles_fiscaux_historique', true);
    }
}
