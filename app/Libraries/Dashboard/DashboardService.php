<?php

namespace App\Libraries\Dashboard;

use App\Libraries\Score\CalculScore;
use Config\Database;
use DateTime;
use RuntimeException;

/**
 * Prépare toutes les données du dashboard de risque pour une société et une période.
 * Rien n'est calculé côté interface : le bloc « graphiques » est prêt à être passé à Chart.js.
 */
class DashboardService
{
    /** Impôt concerné par chaque règle (R6 est transversale). */
    public const IMPOT_PAR_REGLE = ['R1' => 'TVA', 'R2' => 'TVA', 'R3' => 'IRSA', 'R4' => 'IRSA', 'R5' => 'Retenues', 'R6' => 'Comptabilité'];

    private const GRAVITES = ['info', 'moyenne', 'elevee', 'bloquante'];
    private const STATUTS  = ['ouverte', 'justifiee', 'corrigee', 'ignoree'];

    /** @return array<string,mixed> */
    public function donnees(int $societeId, int $periodeId, int $nbPeriodes = 12): array
    {
        $db      = Database::connect();
        $societe = $db->table('societes')->where('id', $societeId)->get()->getRowArray();
        $periode = $db->table('periodes_fiscales')->where(['id' => $periodeId, 'societe_id' => $societeId])->get()->getRowArray();
        if ($societe === null || $periode === null) {
            throw new RuntimeException('Société ou période introuvable.');
        }

        $regles = $db->table('regles_controle')->where('active', 1)->orderBy('code')->get()->getResultArray();

        $toutes = $db->table('anomalies a')
            ->select('a.id, a.periode_id, a.severite, a.statut, a.montant_concerne, a.message, r.code AS regle_code')
            ->join('regles_controle r', 'r.id = a.regle_id')
            ->where('a.societe_id', $societeId)->get()->getResultArray();
        $courantes = array_values(array_filter($toutes, fn ($a) => (int) $a['periode_id'] === $periodeId));

        // ---- Chiffres clés ---------------------------------------------------------------------
        $ouvertes   = array_filter($courantes, fn ($a) => $a['statut'] === 'ouverte');
        $bloquantes = array_filter($ouvertes, fn ($a) => $a['severite'] === 'bloquante');
        $total      = count($courantes);

        $kpi = [
            'nb_anomalies'             => $total,
            'nb_ouvertes'              => count($ouvertes),
            'nb_bloquantes_ouvertes'   => count($bloquantes),
            'montant_concerne_ouvert'  => round(array_sum(array_column($ouvertes, 'montant_concerne')), 2),
            'taux_traitement'          => $total === 0 ? 100 : (int) round(100 * ($total - count($ouvertes)) / $total),
            'validation_bloquee'       => count($bloquantes) > 0,
        ];

        // ---- Regroupements des anomalies de la période ----------------------------------------
        $parRegle = [];
        foreach ($regles as $r) {
            $mine = array_filter($courantes, fn ($a) => $a['regle_code'] === $r['code']);
            $parRegle[] = [
                'code' => $r['code'], 'intitule' => $r['intitule'], 'impot' => self::IMPOT_PAR_REGLE[$r['code']] ?? 'Autre',
                'nb' => count($mine), 'nb_ouvertes' => count(array_filter($mine, fn ($a) => $a['statut'] === 'ouverte')),
                'montant' => round(array_sum(array_column($mine, 'montant_concerne')), 2),
            ];
        }

        $parGravite = array_map(fn ($g) => ['gravite' => $g, 'nb' => count(array_filter($courantes, fn ($a) => $a['severite'] === $g))], self::GRAVITES);
        $parStatut  = array_map(fn ($s) => ['statut' => $s, 'nb' => count(array_filter($courantes, fn ($a) => $a['statut'] === $s))], self::STATUTS);

        $parImpot = [];
        foreach ($parRegle as $r) {
            $parImpot[$r['impot']] ??= ['impot' => $r['impot'], 'nb' => 0, 'montant' => 0.0];
            $parImpot[$r['impot']]['nb']      += $r['nb'];
            $parImpot[$r['impot']]['montant'] += $r['montant'];
        }
        $parImpot = array_values($parImpot);

        // ---- Plus grosses anomalies encore ouvertes -------------------------------------------
        $top = array_values($ouvertes);
        usort($top, fn ($a, $b) => (float) $b['montant_concerne'] <=> (float) $a['montant_concerne']);
        $top = array_map(fn ($a) => [
            'id' => (int) $a['id'], 'regle' => $a['regle_code'], 'severite' => $a['severite'],
            'montant' => (float) $a['montant_concerne'], 'message' => $a['message'],
        ], array_slice($top, 0, 5));

        // ---- Évolution du score et heatmap sur les dernières périodes -------------------------
        $periodes = $db->table('periodes_fiscales')->where('societe_id', $societeId)
            ->orderBy('annee', 'DESC')->orderBy('mois', 'DESC')->get($nbPeriodes)->getResultArray();
        $periodes = array_reverse($periodes);

        $evolution = [];
        foreach ($periodes as $p) {
            $s = $this->scoreDe($societeId, (int) $p['id']);
            $evolution[] = ['periode_id' => (int) $p['id'], 'periode' => sprintf('%02d/%d', $p['mois'], $p['annee']), 'score' => $s['score'], 'niveau' => $s['niveau']];
        }

        $heat = [];
        foreach ($regles as $r) {
            $ligne = [];
            foreach ($periodes as $p) {
                $ligne[] = count(array_filter($toutes, fn ($a) => (int) $a['periode_id'] === (int) $p['id'] && $a['regle_code'] === $r['code']));
            }
            $heat[] = $ligne;
        }

        // ---- Score actuel et comparaison avec la période précédente --------------------------
        $score  = $this->scoreDe($societeId, $periodeId);
        $indice = array_search($periodeId, array_column($evolution, 'periode_id'), true);
        $precedent = ($indice !== false && $indice > 0) ? $evolution[$indice - 1] : null;

        return [
            'societe'   => ['id' => (int) $societe['id'], 'raison_sociale' => $societe['raison_sociale']],
            'periode'   => ['id' => $periodeId, 'libelle' => sprintf('%02d/%d', $periode['mois'], $periode['annee']), 'statut' => $periode['statut']],
            'score'     => [
                'valeur' => $score['score'], 'niveau' => $score['niveau'], 'facteur_historique' => $score['facteur_historique'],
                'detail' => $score['detail'],
                'precedent' => $precedent, 'variation' => $precedent === null ? null : round($score['score'] - $precedent['score'], 1),
            ],
            'kpi'              => $kpi,
            'par_regle'        => $parRegle,
            'par_gravite'      => $parGravite,
            'par_statut'       => $parStatut,
            'par_impot'        => $parImpot,
            'top_anomalies'    => $top,
            'evolution_score'  => $evolution,
            'echeances'        => $this->echeances($societeId),
            // Prêt pour Chart.js : labels + data, sans retraitement.
            'graphiques'       => [
                'evolution_score' => ['labels' => array_column($evolution, 'periode'), 'data' => array_column($evolution, 'score')],
                'par_regle'       => ['labels' => array_column($parRegle, 'code'), 'data' => array_column($parRegle, 'nb')],
                'par_gravite'     => ['labels' => array_column($parGravite, 'gravite'), 'data' => array_column($parGravite, 'nb')],
                'par_statut'      => ['labels' => array_column($parStatut, 'statut'), 'data' => array_column($parStatut, 'nb')],
                'par_impot'       => ['labels' => array_column($parImpot, 'impot'), 'data' => array_column($parImpot, 'nb')],
                'heatmap'         => ['regles' => array_column($regles, 'code'), 'periodes' => array_column($evolution, 'periode'), 'valeurs' => $heat],
            ],
        ];
    }

    /**
     * Dernier score enregistré pour la période, sinon calculé à la volée (sans enregistrer).
     *
     * @return array{score:float,niveau:string,facteur_historique:float,detail:array<string,mixed>}
     */
    private function scoreDe(int $societeId, int $periodeId): array
    {
        $row = Database::connect()->table('scores_risque')->where(['societe_id' => $societeId, 'periode_id' => $periodeId])
            ->orderBy('id', 'DESC')->get(1)->getRowArray();

        if ($row !== null) {
            return [
                'score' => (float) $row['score'], 'niveau' => $row['niveau'], 'facteur_historique' => (float) $row['facteur_historique'],
                'detail' => json_decode((string) $row['detail'], true) ?? [],
            ];
        }

        $calc = (new CalculScore())->calculer($societeId, $periodeId, false);

        return ['score' => $calc['score'], 'niveau' => $calc['niveau'], 'facteur_historique' => $calc['facteur_historique'], 'detail' => $calc['detail']];
    }

    /**
     * Déclarations avec leur échéance : en retard, à moins de 7 jours (alerte), à venir, ou terminée.
     *
     * @return list<array<string,mixed>>
     */
    private function echeances(int $societeId): array
    {
        $rows = Database::connect()->table('declarations d')
            ->select('d.id, d.type, d.statut, d.montant_total, d.date_echeance, p.mois, p.annee')
            ->join('periodes_fiscales p', 'p.id = d.periode_id')
            ->where('d.societe_id', $societeId)->orderBy('d.date_echeance')->get()->getResultArray();

        $aujourdhui = new DateTime('today');
        $out = [];
        foreach ($rows as $r) {
            $termine = in_array($r['statut'], ['deposee', 'archivee'], true);
            $jours   = $r['date_echeance'] === null ? null : (int) $aujourdhui->diff(new DateTime($r['date_echeance']))->format('%r%a');
            $etat    = $termine ? 'termine' : ($jours === null ? 'ok' : ($jours < 0 ? 'en_retard' : ($jours <= 7 ? 'alerte' : 'ok')));

            $out[] = [
                'declaration_id' => (int) $r['id'], 'type' => $r['type'], 'periode' => sprintf('%02d/%d', $r['mois'], $r['annee']),
                'statut' => $r['statut'], 'montant' => (float) $r['montant_total'], 'date_echeance' => $r['date_echeance'],
                'jours_restants' => $jours, 'etat' => $etat,
            ];
        }

        return $out;
    }
}
