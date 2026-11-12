<?php

namespace App\Libraries\Regles;

/**
 * R1 - TVA collectée conforme au taux en vigueur.
 * Pour chaque écriture qui comporte une ligne de TVA collectée : TVA = base HT x taux normal à la date.
 * Base HT = crédits des comptes de classe 7 de l'écriture.
 * Base légale : Code des impôts art. 06.01.12 (taux), 06.01.11 (base).
 */
class R1TvaCollectee extends RegleControle
{
    public function code(): string
    {
        return 'R1';
    }

    public function executer(ContexteControle $ctx, array $params): array
    {
        $tolerance = (float) ($params['tolerance_absolue'] ?? 100);
        $tauxCode  = (string) ($params['taux_code'] ?? 'TVA_NORMAL');
        $resultats = [];

        foreach ($ctx->lignesParEcriture() as $ecritureId => $lignes) {
            $ecriture  = $ctx->ecritures()[$ecritureId];
            $tvaLignes = array_filter($lignes, fn ($l) => $l['role_fiscal'] === 'tva_collectee');
            if ($tvaLignes === []) {
                continue;
            }

            $baseHt = 0.0;
            $preuves = [];
            foreach ($lignes as $l) {
                if (str_starts_with((string) $l['compte_numero'], '7')) {
                    $baseHt  += $l['credit'] - $l['debit'];
                    $preuves[] = $this->preuveLigne($l, $ecriture['numero_piece']);
                }
            }

            $tvaComptabilisee = 0.0;
            foreach ($tvaLignes as $l) {
                $tvaComptabilisee += $l['credit'] - $l['debit'];
                $preuves[] = $this->preuveLigne($l, $ecriture['numero_piece']);
            }

            $taux = $ctx->taux($tauxCode, $ecriture['date_ecriture']);
            if ($taux === null) {
                continue;
            }

            $attendue = round($baseHt * $taux / 100, 2);
            $ecart    = $tvaComptabilisee - $attendue;

            if (abs($ecart) > $tolerance) {
                $resultats[] = new ResultatAnomalie(
                    abs($ecart),
                    'ecriture',
                    $ecritureId,
                    sprintf(
                        'Écriture %s : TVA collectée de %s au lieu de %s (%s %% de %s HT).',
                        $ecriture['numero_piece'],
                        $this->ar($tvaComptabilisee),
                        $this->ar($attendue),
                        rtrim(rtrim(number_format($taux, 2, ',', ''), '0'), ','),
                        $this->ar($baseHt)
                    ),
                    $preuves
                );
            }
        }

        return $resultats;
    }
}
