<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Référentiel : taux, montants et paramètres légaux versionnés par date d'effet
 */
class CreateTauxFiscaux extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'libelle' => ['type' => 'VARCHAR', 'constraint' => 150],
            'valeur' => ['type' => 'DECIMAL', 'constraint' => '15,4', 'default' => 0],
            'unite' => ['type' => 'ENUM', 'constraint' => ['pourcent', 'montant', 'coefficient', 'jour'], 'default' => 'pourcent'],
            'date_effet' => ['type' => 'DATE', 'null' => false],
            'date_fin' => ['type' => 'DATE', 'null' => true],
            'source_legale' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'verifie' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('code');
        $this->forge->addUniqueKey(['code', 'date_effet']);
        $this->forge->createTable('taux_fiscaux', true);
    }

    public function down()
    {
        $this->forge->dropTable('taux_fiscaux', true);
    }
}
