<?php

namespace App\Libraries\Regles;

/**
 * R6 - Périodes et intégrité des écritures.
 *  - période ouverte ;
 *  - chaque écriture équilibrée (total débit = total crédit) ;
 *  - date de l'écriture comprise dans la période.
 * La non-duplication des déclarations est garantie par la contrainte d'unicité (société, période, type).
 * Base légale : Code des impôts art. 06.01.26 (comptabilité régulière) ; Plan Comptable Général.
 */
class R6Periodes extends RegleControle
{
    public function code(): string
    {
        return 'R6';
    }

    public function executer(ContexteControle $ctx, array $params): array
    {
        $tolerance   = (float) ($params['tolerance_absolue'] ?? 0);
        $periodeOuv  = (bool) ($params['exiger_periode_ouverte'] ?? true);
        $horsPeriode = (bool) ($params['controler_hors_periode'] ?? true);
        $periode     = $ctx->periode;
        $resultats   = [];

        if ($periodeOuv && $periode['statut'] !== 'ouverte') {
            $resultats[] = new ResultatAnomalie(
                0.0,
                'periode',
                (int) $periode['id'],
                sprintf('La période %02d/%d est « %s » : des écritures y sont saisies alors qu\'elle n\'est pas ouverte.', $periode['mois'], $periode['annee'], $periode['statut'])
            );
        }

        foreach ($ctx->lignesParEcriture() as $ecritureId => $lignes) {
            $ecriture = $ctx->ecritures()[$ecritureId];
            $debit    = array_sum(array_column($lignes, 'debit'));
            $credit   = array_sum(array_column($lignes, 'credit'));

            if (abs($debit - $credit) > $tolerance) {
                $preuves = array_map(fn ($l) => $this->preuveLigne($l, $ecriture['numero_piece']), $lignes);
                $resultats[] = new ResultatAnomalie(
                    abs($debit - $credit),
                    'ecriture',
                    $ecritureId,
                    sprintf('Écriture %s déséquilibrée : débit %s, crédit %s.', $ecriture['numero_piece'], $this->ar($debit), $this->ar($credit)),
                    $preuves
                );
            }

            if ($horsPeriode && ($ecriture['date_ecriture'] < $periode['date_debut'] || $ecriture['date_ecriture'] > $periode['date_fin'])) {
                $resultats[] = new ResultatAnomalie(
                    $debit,
                    'ecriture',
                    $ecritureId,
                    sprintf(
                        'Écriture %s datée du %s, hors de la période %s au %s.',
                        $ecriture['numero_piece'],
                        date('d/m/Y', strtotime($ecriture['date_ecriture'])),
                        date('d/m/Y', strtotime($periode['date_debut'])),
                        date('d/m/Y', strtotime($periode['date_fin']))
                    ),
                    array_map(fn ($l) => $this->preuveLigne($l, $ecriture['numero_piece']), $lignes)
                );
            }
        }

        return $resultats;
    }
}
