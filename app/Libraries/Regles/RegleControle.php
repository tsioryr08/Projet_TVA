<?php

namespace App\Libraries\Regles;

/**
 * Classe de base de toutes les règles de contrôle.
 *
 * Pour ajouter une règle : créer une classe qui étend RegleControle, l'enregistrer dans
 * RuleEngine::REGLES, et insérer sa ligne dans la table regles_controle (seuils et poids en base).
 */
abstract class RegleControle
{
    /** Code de la règle, identique à regles_controle.code (ex. 'R1'). */
    abstract public function code(): string;

    /**
     * @param array<string,mixed> $params Paramètres lus dans regles_controle.parametres (JSON)
     *
     * @return list<ResultatAnomalie>
     */
    abstract public function executer(ContexteControle $ctx, array $params): array;

    /** Formate un montant en Ariary pour les messages. */
    protected function ar(float $montant): string
    {
        return number_format($montant, 0, ',', ' ') . ' Ar';
    }

    /**
     * Construit un élément de preuve à partir d'une ligne d'écriture (avec infos de compte).
     *
     * @param array<string,mixed> $ligne
     *
     * @return array{table:string,id:?int,libelle:string,montant:float}
     */
    protected function preuveLigne(array $ligne, string $piece): array
    {
        $sens = (float) $ligne['debit'] > 0 ? (float) $ligne['debit'] : (float) $ligne['credit'];

        return [
            'table'   => 'lignes_ecriture',
            'id'      => (int) $ligne['id'],
            'libelle' => $piece . ' - compte ' . $ligne['compte_numero'] . ' ' . $ligne['compte_libelle']
                         . ((float) $ligne['debit'] > 0 ? ' (débit)' : ' (crédit)'),
            'montant' => $sens,
        ];
    }
}
