<?php

namespace App\Libraries\Regles;

/**
 * R2 - TVA déductible justifiée, sans doublon de facture.
 *  - justificatif : document lié et fournisseur avec NIF valide (facture d'un assujetti) ;
 *  - doublon : même tiers + même numéro de pièce + même montant dans le journal d'achats.
 *    Hypothèse : numero_piece contient le numéro de facture du fournisseur.
 * Le paiement bancaire exigé par l'art. 06.01.17 n'est pas contrôlé (aucune donnée de paiement en base).
 * Base légale : Code des impôts art. 06.01.17 1°, 06.01.18, 06.01.21 ; CPF art. IV-21 (à lire).
 */
class R2TvaDeductible extends RegleControle
{
    public function code(): string
    {
        return 'R2';
    }

    public function executer(ContexteControle $ctx, array $params): array
    {
        $exigerDocument = (bool) ($params['exiger_document'] ?? true);
        $exigerNif      = (bool) ($params['exiger_nif_valide'] ?? true);
        $doublons       = (bool) ($params['detecter_doublons'] ?? true);
        $journalAchats  = (string) ($params['journal_achats'] ?? 'AC');
        $resultats      = [];
        $vues           = [];

        foreach ($ctx->lignesParEcriture() as $ecritureId => $lignes) {
            $ecriture = $ctx->ecritures()[$ecritureId];
            $tvaDed   = 0.0;
            $preuves  = [];
            $totalDebit = 0.0;

            foreach ($lignes as $l) {
                $totalDebit += $l['debit'];
                if ($l['role_fiscal'] === 'tva_deductible') {
                    $tvaDed   += $l['debit'] - $l['credit'];
                    $preuves[] = $this->preuveLigne($l, $ecriture['numero_piece']);
                }
            }

            // --- Doublon de facture (journal d'achats, tiers renseigné) ---
            if ($doublons && $ecriture['journal'] === $journalAchats && $ecriture['tiers_id'] !== null) {
                $cle = $ecriture['tiers_id'] . '|' . $ecriture['numero_piece'] . '|' . round($totalDebit, 2);
                if (isset($vues[$cle])) {
                    // Montant à risque : la TVA déduite en double (à défaut, le montant de l'écriture).
                    $resultats[] = new ResultatAnomalie(
                        $tvaDed > 0 ? $tvaDed : $totalDebit,
                        'ecriture',
                        $ecritureId,
                        sprintf(
                            'Écriture %s : facture déjà comptabilisée (pièce %s, %s) pour le même fournisseur.',
                            $ecriture['numero_piece'],
                            $ecriture['numero_piece'],
                            $this->ar($totalDebit)
                        ),
                        array_merge($preuves, [['table' => 'ecritures', 'id' => $vues[$cle], 'libelle' => 'Première comptabilisation de la même facture', 'montant' => $totalDebit]])
                    );
                } else {
                    $vues[$cle] = $ecritureId;
                }
            }

            // --- Justificatif de la TVA déduite ---
            if ($tvaDed <= 0) {
                continue;
            }

            $manques = [];
            if ($exigerDocument && empty($ecriture['document_id'])) {
                $manques[] = 'aucun document lié';
            }
            if ($exigerNif) {
                $tiers = $ecriture['tiers_id'] !== null ? ($ctx->tiers()[(int) $ecriture['tiers_id']] ?? null) : null;
                if ($tiers === null || empty($tiers['nif']) || (int) $tiers['nif_valide'] !== 1) {
                    $manques[] = 'NIF du fournisseur absent ou non valide';
                }
            }

            if ($manques !== []) {
                $resultats[] = new ResultatAnomalie(
                    $tvaDed,
                    'ecriture',
                    $ecritureId,
                    sprintf('Écriture %s : TVA déduite de %s sans justificatif complet (%s).', $ecriture['numero_piece'], $this->ar($tvaDed), implode(' ; ', $manques)),
                    $preuves
                );
            }
        }

        return $resultats;
    }
}
