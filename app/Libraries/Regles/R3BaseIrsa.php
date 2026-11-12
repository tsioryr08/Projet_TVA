<?php

namespace App\Libraries\Regles;

/**
 * R3 - Base IRSA cohérente avec la masse salariale comptabilisée.
 * Somme des salaires bruts des bulletins de la période = total débit du compte de rémunérations (641 par défaut).
 * Base légale : Code des impôts art. 01.03.07 (revenus imposables), 06.01.26 (comptabilité régulière).
 */
class R3BaseIrsa extends RegleControle
{
    public function code(): string
    {
        return 'R3';
    }

    public function executer(ContexteControle $ctx, array $params): array
    {
        $compte    = (string) ($params['compte_masse_salariale'] ?? '641');
        $tolerance = (float) ($params['tolerance_absolue'] ?? 1000);

        $compta  = 0.0;
        $preuves = [];
        foreach ($ctx->lignesParEcriture() as $ecritureId => $lignes) {
            foreach ($lignes as $l) {
                if ((string) $l['compte_numero'] === $compte) {
                    $compta   += $l['debit'] - $l['credit'];
                    $preuves[] = $this->preuveLigne($l, $ctx->ecritures()[$ecritureId]['numero_piece']);
                }
            }
        }

        $brut = 0.0;
        foreach ($ctx->bulletins() as $b) {
            $brut      += (float) $b['salaire_brut'];
            $preuves[] = ['table' => 'bulletins_paie', 'id' => (int) $b['id'], 'libelle' => 'Bulletin ' . $b['matricule'] . ' ' . $b['nom'] . ' (brut)', 'montant' => (float) $b['salaire_brut']];
        }

        if ($brut === 0.0 && $compta === 0.0) {
            return [];
        }

        $ecart = $compta - $brut;
        if (abs($ecart) <= $tolerance) {
            return [];
        }

        return [new ResultatAnomalie(
            abs($ecart),
            'periode',
            (int) $ctx->periode['id'],
            sprintf('Masse salariale comptabilisée (compte %s) de %s contre %s de salaires bruts sur les bulletins.', $compte, $this->ar($compta), $this->ar($brut)),
            $preuves
        )];
    }
}
