<?php

namespace App\Libraries\Saisie;

use App\Libraries\Workflow\AuditService;
use App\Models\ControleFiscalHistoriqueModel;
use Config\Database;

/**
 * Saisie de l'historique des contrôles fiscaux subis par la société.
 * Cet historique alimente le facteur historique du score (+10 si un redressement porte sur une règle qui se déclenche aujourd'hui).
 */
class ControleHistoriqueService
{
    public const TYPES_IMPOT = ['TVA', 'IRSA', 'RETENUE'];

    /**
     * @param array<string,mixed> $saisie date_controle, type_impot, regle_code, motif, redressement_montant
     */
    public function creer(int $societeId, array $saisie, int $utilisateurId): int
    {
        $db   = Database::connect();
        $date = Valeurs::date((string) ($saisie['date_controle'] ?? ''), 'Date du contrôle');
        $type = (string) ($saisie['type_impot'] ?? '');
        if (! in_array($type, self::TYPES_IMPOT, true)) {
            throw new SaisieException('Type d\'impôt invalide.');
        }
        $regle = trim((string) ($saisie['regle_code'] ?? ''));
        if ($regle !== '' && $db->table('regles_controle')->where('code', $regle)->countAllResults() === 0) {
            throw new SaisieException('Règle inconnue : ' . $regle . '.');
        }
        $motif = trim((string) ($saisie['motif'] ?? ''));
        if (mb_strlen($motif) < 5 || mb_strlen($motif) > 1000) {
            throw new SaisieException('Motif du redressement : entre 5 et 1000 caractères.');
        }
        $montant = Valeurs::montant((string) ($saisie['redressement_montant'] ?? '0'), 'Montant du redressement', false);

        $model = new ControleFiscalHistoriqueModel();
        $model->insert([
            'societe_id' => $societeId, 'date_controle' => $date, 'type_impot' => $type,
            'regle_code' => $regle === '' ? null : $regle, 'motif' => $motif, 'redressement_montant' => $montant,
        ]);
        $id = (int) $model->getInsertID();
        if ($id === 0) {
            throw new SaisieException('Enregistrement impossible.');
        }
        (new AuditService())->journaliser($utilisateurId, 'saisie_controle_historique', 'controles_fiscaux_historique', $id, null, [
            'date_controle' => $date, 'type_impot' => $type, 'regle_code' => $regle, 'redressement_montant' => $montant,
        ]);

        return $id;
    }

    /** @return list<array<string,mixed>> */
    public function lister(int $societeId): array
    {
        return Database::connect()->table('controles_fiscaux_historique')->where('societe_id', $societeId)
            ->orderBy('date_controle', 'DESC')->orderBy('id', 'DESC')->get()->getResultArray();
    }

    /** @return list<array<string,mixed>> */
    public function regles(): array
    {
        return Database::connect()->table('regles_controle')->select('code, intitule')->orderBy('code')->get()->getResultArray();
    }
}
