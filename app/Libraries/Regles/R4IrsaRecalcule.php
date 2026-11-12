<?php

namespace App\Libraries\Regles;

/**
 * R4 - IRSA retenu conforme au barème.
 * Recalcul : base arrondie à la centaine inférieure, barème en vigueur au début de la période (par tranche),
 * réduction pour personnes à charge si la base dépasse la première tranche, impôt jamais inférieur au minimum.
 * Base légale : Code des impôts art. 01.03.16, 01.03.19.
 */
class R4IrsaRecalcule extends RegleControle
{
    public function code(): string
    {
        return 'R4';
    }

    public function executer(ContexteControle $ctx, array $params): array
    {
        $tolerance = (float) ($params['tolerance_absolue'] ?? 100);
        $date      = (string) $ctx->periode['date_debut'];
        $bareme    = $ctx->bareme($date);
        if ($bareme === []) {
            return [];
        }

        $minimum   = $ctx->taux('IRSA_MINIMUM', $date) ?? 3000.0;
        $arrondi   = $ctx->taux('IRSA_ARRONDI_BASE', $date) ?? 100.0;
        $reduction = $ctx->taux('IRSA_REDUCTION_CHARGE', $date) ?? 2000.0;
        $seuil     = (float) $bareme[0]['borne_max']; // fin de la tranche à 0 %
        $resultats = [];

        foreach ($ctx->bulletins() as $b) {
            $base = floor((float) $b['base_imposable'] / $arrondi) * $arrondi;

            $impot = 0.0;
            foreach ($bareme as $t) {
                $min = (float) $t['borne_min'];
                $max = $t['borne_max'] === null ? null : (float) $t['borne_max'];
                $haut = $max === null ? $base : min($base, $max);
                if ($haut > $min) {
                    $impot += ($haut - $min) * (float) $t['taux'] / 100;
                }
            }

            if ($base > $seuil) {
                $impot -= (int) $b['nb_charges'] * $reduction;
            }
            $attendu = max($impot, $minimum);
            $retenu  = (float) $b['irsa_retenu'];

            if (abs($retenu - $attendu) > $tolerance) {
                $resultats[] = new ResultatAnomalie(
                    abs($retenu - $attendu),
                    'bulletin_paie',
                    (int) $b['id'],
                    sprintf(
                        'Bulletin %s %s : IRSA retenu de %s au lieu de %s pour une base imposable de %s.',
                        $b['matricule'],
                        $b['nom'],
                        $this->ar($retenu),
                        $this->ar($attendu),
                        $this->ar($base)
                    ),
                    [
                        ['table' => 'bulletins_paie', 'id' => (int) $b['id'], 'libelle' => 'IRSA retenu sur le bulletin', 'montant' => $retenu],
                        ['table' => 'baremes_irsa', 'id' => null, 'libelle' => 'IRSA recalculé selon le barème en vigueur', 'montant' => $attendu],
                    ]
                );
            }
        }

        return $resultats;
    }
}
