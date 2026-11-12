<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Gouvernance : journal d'audit avec chaînage de hash
 */
class CreateJournalAudit extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'utilisateur_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 50],
            'table_cible' => ['type' => 'VARCHAR', 'constraint' => 60],
            'enregistrement_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'donnees_avant' => ['type' => 'JSON', 'null' => true],
            'donnees_apres' => ['type' => 'JSON', 'null' => true],
            'hash_precedent' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'hash_courant' => ['type' => 'VARCHAR', 'constraint' => 64],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('table_cible');
        $this->forge->addKey('enregistrement_id');
        $this->forge->addForeignKey('utilisateur_id', 'utilisateurs', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('journal_audit', true);
    }

    public function down()
    {
        $this->forge->dropTable('journal_audit', true);
    }
}
