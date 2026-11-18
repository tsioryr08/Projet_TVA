<?php

namespace App\Libraries\Anomalies;

use App\Libraries\Saisie\SaisieException;
use App\Libraries\Saisie\Valeurs;
use App\Libraries\Workflow\AuditService;
use Config\Database;

/**
 * Rectification des données à l'origine d'une anomalie, depuis l'application (sans script) :
 *  - une écriture comptable : date, numéro de pièce, libellé, montants des lignes (l'écriture doit rester équilibrée),
 *    document justificatif, NIF du tiers ;
 *  - l'IRSA retenu sur un bulletin de paie ;
 *  - la suppression d'une écriture en double (motif obligatoire).
 * Chaque rectification est faite dans une transaction et journalisée (avant / après) dans le journal d'audit.
 * Elle ne ferme pas l'anomalie : la personne clique ensuite sur « Marquer corrigée », et le moteur vérifie que tout est réglé.
 */
class RectificationService
{
    /**
     * @return array<string,mixed> anomalie, type (ecriture|bulletin), données à afficher, modifiable (+ raison si non)
     */
    public function cible(int $anomalieId): array
    {
        $db = Database::connect();
        $a  = $db->table('anomalies a')->select('a.*, r.code AS regle_code')->join('regles_controle r', 'r.id = a.regle_id')
            ->where('a.id', $anomalieId)->get()->getRowArray() ?? throw new AnomalieException('Anomalie introuvable.');

        $res = ['anomalie' => $a, 'type' => null, 'modifiable' => false, 'raison' => null];
        if (! in_array($a['statut'], ['ouverte', 'justifiee'], true)) {
            $res['raison'] = 'Cette anomalie est déjà ' . ($a['statut'] === 'corrigee' ? 'corrigée' : 'ignorée') . '.';
        }
        $periode = $db->table('periodes_fiscales')->where('id', $a['periode_id'])->get()->getRowArray();
        if ($periode !== null && $periode['statut'] !== 'ouverte') {
            $res['raison'] = 'La période est ' . ($periode['statut'] === 'cloturee' ? 'clôturée' : 'déclarée') . ' : les données ne peuvent plus être modifiées.';
        }

        if ($a['reference_type'] === 'bulletin_paie' && $a['reference_id'] !== null) {
            $b = $db->table('bulletins_paie b')->select('b.*, e.nom, e.prenom, e.matricule')->join('employes e', 'e.id = b.employe_id')
                ->where('b.id', $a['reference_id'])->get()->getRowArray();
            if ($b === null) {
                return $res;
            }
            $res['type'] = 'bulletin';
            $res['bulletin'] = $b;
            $res['modifiable'] = $res['raison'] === null;

            return $res;
        }

        $ecritureId = null;
        if ($a['reference_type'] === 'ecriture' && $a['reference_id'] !== null) {
            $ecritureId = (int) $a['reference_id'];
        } else { // règle portant sur la période (ex. masse salariale) : on part de la ligne comptable citée en preuve
            $p = $db->table('anomalie_preuves')->where(['anomalie_id' => $anomalieId, 'table_source' => 'lignes_ecriture'])->orderBy('id')->get(1)->getRowArray();
            if ($p !== null) {
                $l          = $db->table('lignes_ecriture')->where('id', $p['enregistrement_id'])->get()->getRowArray();
                $ecritureId = $l === null ? null : (int) $l['ecriture_id'];
            }
        }
        $e = $ecritureId === null ? null : $db->table('ecritures')->where(['id' => $ecritureId, 'societe_id' => $a['societe_id']])->get()->getRowArray();
        if ($e === null) {
            return $res;
        }
        $res['type']      = 'ecriture';
        $res['ecriture']  = $e;
        $res['lignes']    = $db->table('lignes_ecriture l')->select('l.*, p.numero AS compte, p.libelle AS compte_libelle')
            ->join('plan_comptable p', 'p.id = l.compte_id', 'left')->where('l.ecriture_id', $ecritureId)->orderBy('l.id')->get()->getResultArray();
        $res['documents'] = $db->table('documents')->select('id, nom_fichier, type_document')->where('societe_id', $a['societe_id'])->orderBy('id')->get()->getResultArray();
        $res['tiers']     = $e['tiers_id'] === null ? null : $db->table('tiers')->where(['id' => $e['tiers_id'], 'societe_id' => $a['societe_id']])->get()->getRowArray();
        // doublon : la preuve cite la première écriture ; supprimer est alors proposé
        $res['doublon']   = $a['regle_code'] === 'R2' && $db->table('anomalie_preuves')->where(['anomalie_id' => $anomalieId, 'table_source' => 'ecritures'])->countAllResults() > 0;
        $res['modifiable'] = $res['raison'] === null;

        return $res;
    }

    /** @param array<string,mixed> $post */
    public function rectifierEcriture(int $anomalieId, array $post, int $utilisateurId): string
    {
        $c = $this->cibleModifiable($anomalieId, 'ecriture');
        $e = $c['ecriture'];
        $db = Database::connect();

        try {
            $date    = Valeurs::date((string) ($post['date_ecriture'] ?? ''), 'Date de l\'écriture');
        } catch (SaisieException $ex) {
            throw new AnomalieException($ex->getMessage());
        }
        $piece   = trim((string) ($post['numero_piece'] ?? ''));
        $libelle = trim((string) ($post['libelle'] ?? ''));
        if ($piece === '' || mb_strlen($piece) > 50) {
            throw new AnomalieException('Numéro de pièce : 1 à 50 caractères.');
        }
        if (mb_strlen($libelle) > 255) {
            throw new AnomalieException('Libellé : 255 caractères au plus.');
        }

        // Lignes : montants nets (débit OU crédit), écriture équilibrée.
        $nouvelles = [];
        $totD = $totC = 0;
        foreach ($c['lignes'] as $l) {
            $d = $this->centimes($post['debit'][$l['id']] ?? '', 'Débit');
            $k = $this->centimes($post['credit'][$l['id']] ?? '', 'Crédit');
            if ($d > 0 && $k > 0) {
                throw new AnomalieException('Compte ' . $l['compte'] . ' : une ligne est soit au débit, soit au crédit, pas les deux.');
            }
            $nouvelles[(int) $l['id']] = [$d, $k];
            $totD += $d;
            $totC += $k;
        }
        if ($totD !== $totC) {
            throw new AnomalieException(sprintf('Écriture déséquilibrée : débit %s Ar, crédit %s Ar. Rééquilibrez avant d\'enregistrer.', number_format($totD / 100, 0, ',', ' '), number_format($totC / 100, 0, ',', ' ')));
        }
        if ($totD === 0) {
            throw new AnomalieException('Écriture vide : au moins une ligne doit avoir un montant.');
        }

        $docId = (int) ($post['document_id'] ?? 0);
        if ($docId > 0 && $db->table('documents')->where(['id' => $docId, 'societe_id' => $c['anomalie']['societe_id']])->countAllResults() === 0) {
            throw new AnomalieException('Ce document n\'appartient pas à la société.');
        }
        $docId = $docId > 0 ? $docId : null;

        $nif = strtoupper(str_replace(' ', '', trim((string) ($post['tiers_nif'] ?? ''))));
        if ($c['tiers'] !== null && $nif !== '' && $nif !== (string) $c['tiers']['nif'] && ! preg_match('/^[0-9A-Z]{5,30}$/', $nif)) {
            throw new AnomalieException('NIF du tiers : 5 à 30 chiffres ou lettres, sans signe.');
        }

        // Ce qui change réellement (avant / après pour l'audit)
        $avant = ['date_ecriture' => $e['date_ecriture'], 'numero_piece' => $e['numero_piece'], 'libelle' => $e['libelle'], 'document_id' => $e['document_id'], 'lignes' => []];
        $apres = ['date_ecriture' => $date, 'numero_piece' => $piece, 'libelle' => $libelle, 'document_id' => $docId, 'lignes' => []];
        foreach ($c['lignes'] as $l) {
            $avant['lignes'][$l['compte']] = ['debit' => number_format((float) $l['debit'], 2, '.', ''), 'credit' => number_format((float) $l['credit'], 2, '.', '')];
            [$d, $k] = $nouvelles[(int) $l['id']];
            $apres['lignes'][$l['compte']] = ['debit' => number_format($d / 100, 2, '.', ''), 'credit' => number_format($k / 100, 2, '.', '')];
        }
        $nifChange = $c['tiers'] !== null && $nif !== '' && $nif !== (string) $c['tiers']['nif'];
        if ($nifChange) {
            $avant['tiers_nif'] = $c['tiers']['nif'];
            $apres['tiers_nif'] = $nif;
        }
        if ($avant === $apres) {
            throw new AnomalieException('Aucune modification : rien n\'a été changé.');
        }

        $db->transStart();
        $db->table('ecritures')->where('id', $e['id'])->update(['date_ecriture' => $date, 'numero_piece' => $piece, 'libelle' => $libelle, 'document_id' => $docId, 'updated_at' => date('Y-m-d H:i:s')]);
        foreach ($nouvelles as $ligneId => [$d, $k]) {
            $db->table('lignes_ecriture')->where(['id' => $ligneId, 'ecriture_id' => $e['id']])->update(['debit' => number_format($d / 100, 2, '.', ''), 'credit' => number_format($k / 100, 2, '.', '')]);
        }
        if ($nifChange) {
            $db->table('tiers')->where('id', $c['tiers']['id'])->update(['nif' => $nif, 'nif_valide' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            throw new AnomalieException('Enregistrement impossible : rien n\'a été modifié.');
        }

        (new AuditService())->journaliser($utilisateurId, 'rectifier_ecriture', 'ecritures', (int) $e['id'], $avant, $apres + ['anomalie_id' => (string) $anomalieId]);

        return sprintf('Écriture %s rectifiée et journalisée.', $piece);
    }

    /** @param array<string,mixed> $post */
    public function rectifierBulletin(int $anomalieId, array $post, int $utilisateurId): string
    {
        $c = $this->cibleModifiable($anomalieId, 'bulletin');
        $b = $c['bulletin'];
        try {
            $irsa = Valeurs::montant((string) ($post['irsa_retenu'] ?? ''), 'IRSA retenu', false);
        } catch (SaisieException $ex) {
            throw new AnomalieException($ex->getMessage());
        }
        if ((float) $irsa > (float) $b['base_imposable']) {
            throw new AnomalieException('L\'IRSA ne peut pas dépasser la base imposable.');
        }
        if ($irsa === number_format((float) $b['irsa_retenu'], 2, '.', '')) {
            throw new AnomalieException('Aucune modification : l\'IRSA est inchangé.');
        }
        // Le net à payer suit : ce qui est retenu en moins est payé en plus (et inversement).
        $net = number_format((float) $b['net_a_payer'] + (float) $b['irsa_retenu'] - (float) $irsa, 2, '.', '');
        if ((float) $net < 0) {
            throw new AnomalieException('Le net à payer deviendrait négatif.');
        }

        $db = Database::connect();
        $db->transStart();
        $db->table('bulletins_paie')->where('id', $b['id'])->update(['irsa_retenu' => $irsa, 'net_a_payer' => $net, 'updated_at' => date('Y-m-d H:i:s')]);
        $db->transComplete();
        if (! $db->transStatus()) {
            throw new AnomalieException('Enregistrement impossible : rien n\'a été modifié.');
        }
        (new AuditService())->journaliser($utilisateurId, 'rectifier_bulletin', 'bulletins_paie', (int) $b['id'],
            ['irsa_retenu' => number_format((float) $b['irsa_retenu'], 2, '.', ''), 'net_a_payer' => number_format((float) $b['net_a_payer'], 2, '.', '')],
            ['irsa_retenu' => $irsa, 'net_a_payer' => $net, 'anomalie_id' => (string) $anomalieId]);

        return sprintf('Bulletin de %s %s rectifié : IRSA retenu %s Ar.', $b['prenom'], $b['nom'], number_format((float) $irsa, 0, ',', ' '));
    }

    /** Supprime une écriture saisie en double (la première comptabilisation reste). Motif d'au moins 10 caractères. */
    public function supprimerDoublon(int $anomalieId, string $motif, int $utilisateurId): string
    {
        $c = $this->cibleModifiable($anomalieId, 'ecriture');
        if (! $c['doublon']) {
            throw new AnomalieException('Cette anomalie n\'est pas un doublon de facture : l\'écriture ne peut pas être supprimée ici.');
        }
        $motif = trim($motif);
        if (mb_strlen($motif) < 10) {
            throw new AnomalieException('Motif de suppression : 10 caractères minimum.');
        }
        $e  = $c['ecriture'];
        $db = Database::connect();
        $instantane = ['numero_piece' => $e['numero_piece'], 'date_ecriture' => $e['date_ecriture'], 'lignes' => []];
        foreach ($c['lignes'] as $l) {
            $instantane['lignes'][$l['compte']] = ['debit' => number_format((float) $l['debit'], 2, '.', ''), 'credit' => number_format((float) $l['credit'], 2, '.', '')];
        }

        $db->transStart();
        $db->table('lignes_ecriture')->where('ecriture_id', $e['id'])->delete();
        $db->table('ecritures')->where('id', $e['id'])->delete();
        $db->transComplete();
        if (! $db->transStatus()) {
            throw new AnomalieException('Suppression impossible : rien n\'a été modifié.');
        }
        (new AuditService())->journaliser($utilisateurId, 'supprimer_ecriture_doublon', 'ecritures', (int) $e['id'], $instantane, ['motif' => $motif, 'anomalie_id' => (string) $anomalieId]);

        return sprintf('Écriture en double %s supprimée (motif enregistré dans le journal).', $e['numero_piece']);
    }

    /** @return array<string,mixed> */
    private function cibleModifiable(int $anomalieId, string $type): array
    {
        $c = $this->cible($anomalieId);
        if ($c['type'] !== $type) {
            throw new AnomalieException('Aucune donnée modifiable de ce type n\'est rattachée à cette anomalie.');
        }
        if (! $c['modifiable']) {
            throw new AnomalieException((string) $c['raison']);
        }

        return $c;
    }

    /** « 300 000 » ou « 300000,5 » -> centimes entiers (0 si vide). */
    private function centimes(string $brut, string $libelle): int
    {
        if (trim($brut) === '') {
            return 0;
        }
        try {
            return (int) round((float) Valeurs::montant($brut, $libelle, false) * 100);
        } catch (SaisieException $e) {
            throw new AnomalieException($e->getMessage());
        }
    }
}