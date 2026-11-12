<?php

namespace App\Libraries\Regles;

/**
 * R5 - Retenue à la source sur services payés à des non-résidents.
 * Pour une écriture avec un tiers non résident et des charges de services (compte 622 par défaut) :
 * retenue comptabilisée (crédit des comptes de rôle retenue_source) = base x taux de retenue (10 %).
 * Base légale : Code des impôts art. 01.01.14 II-A. L'impôt synthétique sur vendeurs non immatriculés
 * (art. 01.02.02 II) et l'IRSA reversé seront ajoutés après lecture du Code des procédures fiscales.
 */
class R5RetenueSource extends RegleControle
{
    public function code(): string
    {
        return 'R5';
    }

    public function executer(ContexteControle $ctx, array $params): array
    {
        $tolerance = (float) ($params['tolerance_absolue'] ?? 100);
        $compte    = (string) ($params['compte_charge'] ?? '622');
        $tauxCode  = (string) ($params['retenue_taux_code'] ?? 'RETENUE_NON_RESIDENT_SERVICES');
        $resultats = [];

        foreach ($ctx->lignesParEcriture() as $ecritureId => $lignes) {
            $ecriture = $ctx->ecritures()[$ecritureId];
            $tiers    = $ecriture['tiers_id'] !== null ? ($ctx->tiers()[(int) $ecriture['tiers_id']] ?? null) : null;
            if ($tiers === null || (int) $tiers['non_resident'] !== 1) {
                continue;
            }

            $base = 0.0;
            $retenue = 0.0;
            $preuves = [];
            foreach ($lignes as $l) {
                if ((string) $l['compte_numero'] === $compte) {
                    $base     += $l['debit'] - $l['credit'];
                    $preuves[] = $this->preuveLigne($l, $ecriture['numero_piece']);
                } elseif ($l['role_fiscal'] === 'retenue_source') {
                    $retenue  += $l['credit'] - $l['debit'];
                    $preuves[] = $this->preuveLigne($l, $ecriture['numero_piece']);
                }
            }

            if ($base <= 0) {
                continue;
            }

            $taux = $ctx->taux($tauxCode, $ecriture['date_ecriture']);
            if ($taux === null) {
                continue;
            }

            $attendue = round($base * $taux / 100, 2);
            if (abs($retenue - $attendue) > $tolerance) {
                $resultats[] = new ResultatAnomalie(
                    abs($attendue - $retenue),
                    'ecriture',
                    $ecritureId,
                    sprintf(
                        'Écriture %s : retenue à la source de %s au lieu de %s (%s %% de %s) sur un paiement à un non-résident.',
                        $ecriture['numero_piece'],
                        $this->ar($retenue),
                        $this->ar($attendue),
                        rtrim(rtrim(number_format($taux, 2, ',', ''), '0'), ','),
                        $this->ar($base)
                    ),
                    $preuves
                );
            }
        }

        return $resultats;
    }
}
