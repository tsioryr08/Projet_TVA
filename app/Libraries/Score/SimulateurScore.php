<?php

declare(strict_types=1);

namespace App\Libraries\Score;

use App\Libraries\Anomalies\AnomalieService;
use Config\Database;
use InvalidArgumentException;

/**
 * Simulateur « et si je corrige ? » : calcule le score qu'aurait une période si certaines anomalies ouvertes
 * étaient corrigées ou justifiées, SANS RIEN ÉCRIRE en base (ni anomalies, ni scores, ni journal d'audit).
 *
 * Tout le calcul passe par CalculScore::calculer(..., false, $surcharges) : la formule reste donc strictement
 * celle du score réel. Ce service ne fait que des lectures (SELECT).
 *
 * Limite : une simulation « corrigée » suppose que la correction des données est réellement faite. Le vrai
 * traitement (AnomalieService::traiter) vérifie en plus que l'anomalie n'est plus détectée.
 */
class SimulateurScore
{
    /** Statuts simulables pour une anomalie ouverte. */
    public const CHOIX_AUTORISES = ['corrigee', 'justifiee'];

    /**
     * Rôles pouvant justifier une anomalie BLOQUANTE. Même règle que AnomalieService::verifier() :
     * justifier une bloquante lèverait le blocage sans correction des données.
     */
    private const ROLES_JUSTIFICATION_BLOQUANTE = ['responsable_fiscal', 'admin'];

    private CalculScore $calcul;

    public function __construct(?CalculScore $calcul = null)
    {
        $this->calcul = $calcul ?? new CalculScore();
    }

    /** Vrai si ce rôle a le droit de justifier une anomalie de cette gravité. */
    public static function peutJustifier(string $severite, string $role): bool
    {
        return $severite !== 'bloquante' || in_array($role, self::ROLES_JUSTIFICATION_BLOQUANTE, true);
    }

    /**
     * Pour chaque anomalie ouverte : score projeté et gain si elle était seule corrigée, ou seule justifiée.
     * Le gain est positif quand le score baisse. « justifiee » vaut null si le rôle n'a pas le droit de justifier.
     *
     * @return list<array{id:int,regle_code:string,severite:string,message:string,montant_concerne:float,corrigee:array{score:float,niveau:string,gain:float},justifiee:?array{score:float,niveau:string,gain:float}}>
     */
    public function gainsIndividuels(int $societeId, int $periodeId, string $role): array
    {
        $actuel    = $this->calcul->calculer($societeId, $periodeId, false);
        $ouvertes  = (new AnomalieService())->lister($societeId, $periodeId, ['statut' => 'ouverte']);
        $resultats = [];

        foreach ($ouvertes as $a) {
            $id = (int) $a['id'];

            $resultats[] = [
                'id'               => $id,
                'regle_code'       => (string) $a['regle_code'],
                'severite'         => (string) $a['severite'],
                'message'          => (string) $a['message'],
                'montant_concerne' => (float) $a['montant_concerne'],
                'corrigee'         => $this->projection($societeId, $periodeId, $actuel['score'], $id, 'corrigee'),
                'justifiee'        => self::peutJustifier((string) $a['severite'], $role)
                    ? $this->projection($societeId, $periodeId, $actuel['score'], $id, 'justifiee')
                    : null,
            ];
        }

        return $resultats;
    }

    /**
     * Simule un ensemble de choix et compare au score actuel.
     *
     * @param array<int|string,string> $choix [idAnomalie => 'corrigee'|'justifiee']
     *
     * @throws InvalidArgumentException si un choix est invalide (message en français)
     *
     * @return array{score_actuel:float,niveau_actuel:string,score_projete:float,niveau_projete:string,delta:float,bloquantes_avant:int,bloquantes_apres:int,soumettable_apres:bool,nb_corrigees:int,nb_justifiees:int,detail_regles:list<array<string,mixed>>}
     */
    public function simuler(int $societeId, int $periodeId, array $choix, string $role): array
    {
        $surcharges = $this->valider($societeId, $periodeId, $choix, $role);

        $avant = $this->calcul->calculer($societeId, $periodeId, false);
        $apres = $this->calcul->calculer($societeId, $periodeId, false, $surcharges);

        // Détail par règle, avant/après (les deux calculs portent sur les mêmes règles actives).
        $apresParCode = [];
        foreach ($apres['detail']['regles'] as $r) {
            $apresParCode[$r['code']] = $r;
        }
        $detail = [];
        foreach ($avant['detail']['regles'] as $r) {
            $a        = $apresParCode[$r['code']] ?? ['points' => 0.0, 'contribution' => 0.0];
            $detail[] = [
                'code'               => $r['code'],
                'intitule'           => $r['intitule'],
                'nb_anomalies'       => $r['nb_anomalies'],
                'points_avant'       => round((float) $r['points'], 2),
                'points_apres'       => round((float) $a['points'], 2),
                'contribution_avant' => (float) $r['contribution'],
                'contribution_apres' => (float) $a['contribution'],
            ];
        }

        $bloquantesApres = (int) $apres['detail']['anomalies_bloquantes_ouvertes'];
        $valeurs         = array_count_values($surcharges);

        return [
            'score_actuel'      => $avant['score'],
            'niveau_actuel'     => $avant['niveau'],
            'score_projete'     => $apres['score'],
            'niveau_projete'    => $apres['niveau'],
            'delta'             => round($apres['score'] - $avant['score'], 1),
            'bloquantes_avant'  => (int) $avant['detail']['anomalies_bloquantes_ouvertes'],
            'bloquantes_apres'  => $bloquantesApres,
            'soumettable_apres' => $bloquantesApres === 0,
            'nb_corrigees'      => $valeurs['corrigee'] ?? 0,
            'nb_justifiees'     => $valeurs['justifiee'] ?? 0,
            'detail_regles'     => $detail,
        ];
    }

    /**
     * Valide les choix et les convertit en surcharges [id => statut].
     *
     * @param array<int|string,mixed> $choix
     *
     * @return array<int,string>
     */
    private function valider(int $societeId, int $periodeId, array $choix, string $role): array
    {
        $surcharges = [];
        foreach ($choix as $id => $valeur) {
            // Les clés numériques de formulaire arrivent en chaîne (« 12 ») : on les normalise.
            if (! (is_int($id) || (is_string($id) && ctype_digit($id)))) {
                throw new InvalidArgumentException(sprintf('Identifiant d\'anomalie invalide : « %s ».', (string) $id));
            }
            $id = (int) $id;
            if (! is_string($valeur) || ! in_array($valeur, self::CHOIX_AUTORISES, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Choix « %s » non autorisé pour l\'anomalie n° %d : seuls « corrigee » et « justifiee » sont acceptés.',
                    is_string($valeur) ? $valeur : gettype($valeur),
                    $id
                ));
            }
            $surcharges[$id] = $valeur;
        }

        if ($surcharges === []) {
            return [];
        }

        // Lecture seule : on récupère les anomalies demandées avec l'état de leur règle.
        $lignes = Database::connect()->table('anomalies a')
            ->select('a.id, a.societe_id, a.periode_id, a.statut, a.severite, r.active AS regle_active')
            ->join('regles_controle r', 'r.id = a.regle_id')
            ->whereIn('a.id', array_keys($surcharges))
            ->get()->getResultArray();

        $parId = [];
        foreach ($lignes as $l) {
            $parId[(int) $l['id']] = $l;
        }

        foreach ($surcharges as $id => $valeur) {
            $a = $parId[$id] ?? null;
            if ($a === null) {
                throw new InvalidArgumentException(sprintf('L\'anomalie n° %d n\'existe pas.', $id));
            }
            if ((int) $a['societe_id'] !== $societeId || (int) $a['periode_id'] !== $periodeId) {
                throw new InvalidArgumentException(sprintf('L\'anomalie n° %d n\'appartient pas à cette société et à cette période.', $id));
            }
            if (! (int) $a['regle_active']) {
                throw new InvalidArgumentException(sprintf('L\'anomalie n° %d dépend d\'une règle désactivée : elle n\'entre pas dans le score.', $id));
            }
            if ($a['statut'] !== 'ouverte') {
                throw new InvalidArgumentException(sprintf(
                    'L\'anomalie n° %d n\'est pas ouverte (statut actuel : %s) : seules les anomalies ouvertes peuvent être simulées.',
                    $id,
                    $a['statut']
                ));
            }
            if ($valeur === 'justifiee' && ! self::peutJustifier((string) $a['severite'], $role)) {
                throw new InvalidArgumentException(sprintf(
                    'L\'anomalie n° %d est bloquante : elle ne peut être justifiée que par un responsable fiscal ou un administrateur. Choisissez « corrigee » ou demandez-lui.',
                    $id
                ));
            }
        }

        return $surcharges;
    }

    /**
     * Score projeté si UNE seule anomalie change de statut.
     *
     * @return array{score:float,niveau:string,gain:float}
     */
    private function projection(int $societeId, int $periodeId, float $scoreActuel, int $anomalieId, string $statut): array
    {
        $r = $this->calcul->calculer($societeId, $periodeId, false, [$anomalieId => $statut]);

        return ['score' => $r['score'], 'niveau' => $r['niveau'], 'gain' => round($scoreActuel - $r['score'], 1)];
    }
}
