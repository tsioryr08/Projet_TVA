<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Déclarations : en-têtes (TVA, IRSA, retenues)
 */
class CreateDeclarations extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'societe_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'periode_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'type' => ['type' => 'ENUM', 'constraint' => ['TVA', 'IRSA', 'RETENUE'], 'default' => 'TVA'],
            'statut' => ['type' => 'ENUM', 'constraint' => ['brouillon', 'a_corriger', 'a_valider', 'validee', 'deposee', 'archivee'], 'default' => 'brouillon'],
            'montant_total' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => 0],
            'date_echeance' => ['type' => 'DATE', 'null' => true],
            'date_depot' => ['type' => 'DATE', 'null' => true],
            'reference_virement' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'cree_par' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['societe_id', 'periode_id', 'type']);
        $this->forge->addForeignKey('societe_id', 'societes', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('periode_id', 'periodes_fiscales', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('cree_par', 'utilisateurs', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('declarations', true);
    }

    public function down()
    {
        $this->forge->dropTable('declarations', true);
    }
}
