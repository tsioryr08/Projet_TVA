<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Contrôle : anomalies détectées par les règles
 */
class CreateAnomalies extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'societe_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'periode_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'regle_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'severite' => ['type' => 'ENUM', 'constraint' => ['info', 'moyenne', 'elevee', 'bloquante'], 'default' => 'moyenne'],
            'montant_concerne' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'reference_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'reference_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'message' => ['type' => 'TEXT', 'null' => true],
            'action_corrective' => ['type' => 'TEXT', 'null' => true],
            'statut' => ['type' => 'ENUM', 'constraint' => ['ouverte', 'justifiee', 'corrigee', 'ignoree'], 'default' => 'ouverte'],
            'justification' => ['type' => 'TEXT', 'null' => true],
            'detectee_le' => ['type' => 'DATETIME', 'null' => true],
            'traitee_par' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['periode_id', 'regle_id']);
        $this->forge->addKey('societe_id');
        $this->forge->addForeignKey('societe_id', 'societes', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('periode_id', 'periodes_fiscales', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('regle_id', 'regles_controle', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('traitee_par', 'utilisateurs', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('anomalies', true);
    }

    public function down()
    {
        $this->forge->dropTable('anomalies', true);
    }
}
