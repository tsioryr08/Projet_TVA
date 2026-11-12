<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Contrôle : score de risque par période, avec détail explicatif
 */
class CreateScoresRisque extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'societe_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'periode_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'score' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'facteur_historique' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'niveau' => ['type' => 'ENUM', 'constraint' => ['faible', 'moyen', 'eleve', 'critique'], 'default' => 'faible'],
            'detail' => ['type' => 'JSON', 'null' => true],
            'calcule_le' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('societe_id');
        $this->forge->addKey('periode_id');
        $this->forge->addForeignKey('societe_id', 'societes', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('periode_id', 'periodes_fiscales', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('scores_risque', true);
    }

    public function down()
    {
        $this->forge->dropTable('scores_risque', true);
    }
}
