<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Référentiel : périodes fiscales mensuelles
 */
class CreatePeriodesFiscales extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'societe_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'annee' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => false],
            'mois' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true],
            'date_debut' => ['type' => 'DATE', 'null' => false],
            'date_fin' => ['type' => 'DATE', 'null' => false],
            'statut' => ['type' => 'ENUM', 'constraint' => ['ouverte', 'cloturee', 'declaree'], 'default' => 'ouverte'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('societe_id');
        $this->forge->addUniqueKey(['societe_id', 'annee', 'mois']);
        $this->forge->addForeignKey('societe_id', 'societes', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('periodes_fiscales', true);
    }

    public function down()
    {
        $this->forge->dropTable('periodes_fiscales', true);
    }
}
