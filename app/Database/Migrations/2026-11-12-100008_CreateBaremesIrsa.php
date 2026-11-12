<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Référentiel : barème IRSA par tranche, versionné
 */
class CreateBaremesIrsa extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'tranche_numero' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true],
            'borne_min' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'borne_max' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'null' => true],
            'taux' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'date_effet' => ['type' => 'DATE', 'null' => false],
            'date_fin' => ['type' => 'DATE', 'null' => true],
            'source_legale' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'verifie' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tranche_numero', 'date_effet']);
        $this->forge->createTable('baremes_irsa', true);
    }

    public function down()
    {
        $this->forge->dropTable('baremes_irsa', true);
    }
}
