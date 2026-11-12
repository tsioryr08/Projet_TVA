<?php

namespace App\Libraries\Regles;

/**
 * Résultat d'une règle : une anomalie détectée.
 * C'est LE contrat entre le moteur (règles) et le reste du projet (workflow, score, dashboard).
 *
 * $preuves : lignes sources du dossier de preuve, chaque élément étant
 *            ['table' => string, 'id' => ?int, 'libelle' => string, 'montant' => float]
 */
final class ResultatAnomalie
{
    /**
     * @param list<array{table:string,id:?int,libelle:string,montant:float}> $preuves
     */
    public function __construct(
        public readonly float $montantConcerne,
        public readonly string $referenceType,
        public readonly ?int $referenceId,
        public readonly string $message,
        public readonly array $preuves = [],
    ) {
    }
}
