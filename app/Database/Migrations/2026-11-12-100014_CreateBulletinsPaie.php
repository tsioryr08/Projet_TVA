<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Salaires : bulletins de paie
 */
class CreateBulletinsPaie extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'employe_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'periode_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'document_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'salaire_brut' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'cotisation_cnaps_salarie' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'cotisation_ostie_salarie' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'base_imposable' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'reduction_charges' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'irsa_retenu' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'net_a_payer' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'cotisation_cnaps_patronale' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'cotisation_ostie_patronale' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'fmfp_patronale' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['employe_id', 'periode_id']);
        $this->forge->addForeignKey('employe_id', 'employes', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('periode_id', 'periodes_fiscales', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('document_id', 'documents', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('bulletins_paie', true);
    }

    public function down()
    {
        $this->forge->dropTable('bulletins_paie', true);
    }
}
