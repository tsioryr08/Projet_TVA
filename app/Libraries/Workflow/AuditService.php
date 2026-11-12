<?php

namespace App\Libraries\Workflow;

use Config\Database;

/**
 * Journal d'audit à chaînage de hash : chaque ligne contient le hash de la précédente.
 * Modifier ou supprimer une ligne casse la chaîne, ce que verifierChaine() détecte.
 *
 * Les données avant/après ne doivent contenir que des chaînes, entiers ou null (pas de flottants),
 * pour que le hash soit reproductible quel que soit le formatage JSON de la base.
 */
class AuditService
{
    /**
     * @param array<string,mixed>|null $avant
     * @param array<string,mixed>|null $apres
     */
    public function journaliser(?int $utilisateurId, string $action, string $table, ?int $enregistrementId, ?array $avant = null, ?array $apres = null): int
    {
        $db        = Database::connect();
        $dernier   = $db->table('journal_audit')->select('hash_courant')->orderBy('id', 'DESC')->get(1)->getRowArray();
        $precedent = $dernier['hash_courant'] ?? null;
        $avantJson = $avant === null ? null : $this->canonique($avant);
        $apresJson = $apres === null ? null : $this->canonique($apres);
        $date      = date('Y-m-d H:i:s');

        $db->table('journal_audit')->insert([
            'utilisateur_id'    => $utilisateurId,
            'action'            => $action,
            'table_cible'       => $table,
            'enregistrement_id' => $enregistrementId,
            'donnees_avant'     => $avantJson,
            'donnees_apres'     => $apresJson,
            'hash_precedent'    => $precedent,
            'hash_courant'      => $this->hash($precedent, $utilisateurId, $action, $table, $enregistrementId, $avantJson, $apresJson, $date),
            'created_at'        => $date,
        ]);

        return (int) $db->insertID();
    }

    /**
     * @return array{ok:bool,nb:int,premiere_erreur:?int,raison:?string}
     */
    public function verifierChaine(): array
    {
        $rows      = Database::connect()->table('journal_audit')->orderBy('id')->get()->getResultArray();
        $precedent = null;

        foreach ($rows as $r) {
            if (($r['hash_precedent'] ?? null) !== $precedent) {
                return ['ok' => false, 'nb' => count($rows), 'premiere_erreur' => (int) $r['id'], 'raison' => 'chaînage rompu (ligne supprimée ou modifiée avant celle-ci)'];
            }

            $avant = $r['donnees_avant'] === null ? null : $this->canonique(json_decode((string) $r['donnees_avant'], true) ?? []);
            $apres = $r['donnees_apres'] === null ? null : $this->canonique(json_decode((string) $r['donnees_apres'], true) ?? []);
            $attendu = $this->hash($r['hash_precedent'], $r['utilisateur_id'] === null ? null : (int) $r['utilisateur_id'], $r['action'], $r['table_cible'], $r['enregistrement_id'] === null ? null : (int) $r['enregistrement_id'], $avant, $apres, $r['created_at']);

            if ($attendu !== $r['hash_courant']) {
                return ['ok' => false, 'nb' => count($rows), 'premiere_erreur' => (int) $r['id'], 'raison' => 'contenu modifié après coup'];
            }
            $precedent = $r['hash_courant'];
        }

        return ['ok' => true, 'nb' => count($rows), 'premiere_erreur' => null, 'raison' => null];
    }

    private function hash(?string $precedent, ?int $utilisateurId, string $action, string $table, ?int $id, ?string $avant, ?string $apres, string $date): string
    {
        return hash('sha256', implode('|', [$precedent ?? '', $utilisateurId ?? '', $action, $table, $id ?? '', $avant ?? '', $apres ?? '', $date]));
    }

    /** @param array<string,mixed> $donnees */
    private function canonique(array $donnees): string
    {
        $this->trier($donnees);

        return json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param array<string,mixed> $a */
    private function trier(array &$a): void
    {
        ksort($a);
        foreach ($a as &$v) {
            if (is_array($v)) {
                $this->trier($v);
            }
        }
    }
}
