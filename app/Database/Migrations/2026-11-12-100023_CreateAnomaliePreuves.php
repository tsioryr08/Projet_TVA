<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Contrôle : lignes sources d'une anomalie (dossier de preuve)
 */
class CreateAnomaliePreuves extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'anomalie_id'       => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'table_source'      => ['type' => 'VARCHAR', 'constraint' => 60],
            'enregistrement_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'libelle'           => ['type' => 'VARCHAR', 'constraint' => 255],
            'montant'           => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('anomalie_id');
        $this->forge->addForeignKey('anomalie_id', 'anomalies', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('anomalie_preuves', true);
    }

    public function down()
    {
        $this->forge->dropTable('anomalie_preuves', true);
    }
}
