<?php

namespace App\Controllers;

use App\Libraries\Anomalies\AnomalieException;
use App\Libraries\Anomalies\AnomalieService;
use App\Libraries\Regles\RuleEngine;
use App\Libraries\Score\CalculScore;
use App\Libraries\Workflow\AuditService;

/** Liste, détail et traitement des anomalies. */
class Anomalies extends AppController
{
    public function liste()
    {
        $ctx = $this->contexte();
        if ($ctx['periode'] === null) {
            return $this->vue('anomalies/liste', ['anomalies' => [], 'filtres' => [], 'score' => null]);
        }

        $filtres = array_filter(array_intersect_key($this->request->getGet() ?? [], array_flip(['regle', 'severite', 'statut'])), fn ($v) => is_string($v) && $v !== '');
        $s = (int) $ctx['societe']['id'];
        $p = (int) $ctx['periode']['id'];

        return $this->vue('anomalies/liste', [
            'anomalies' => (new AnomalieService())->lister($s, $p, $filtres),
            'filtres'   => $filtres,
            'score'     => (new CalculScore())->calculer($s, $p, false),
            'regles'    => \Config\Database::connect()->table('regles_controle')->select('code')->where('active', 1)->orderBy('code')->get()->getResultArray(),
        ]);
    }

    public function detail(int $id)
    {
        $service = new AnomalieService();
        try {
            $fiche   = $service->detail($id);
            $actions = $service->actionsPossibles($id, $this->utilisateur['id']);
        } catch (AnomalieException $e) {
            return redirect()->to(site_url('anomalies'))->with('erreur', $e->getMessage());
        }

        $rectifiable = false;
        if (in_array($this->utilisateur['role'], ['comptable', 'admin'], true)) {
            $c           = (new \App\Libraries\Anomalies\RectificationService())->cible($id);
            $rectifiable = $c['type'] !== null && $c['modifiable'];
        }

        return $this->vue('anomalies/detail', $fiche + ['actions' => $actions, 'rectifiable' => $rectifiable]);
    }

    public function traiter(int $id)
    {
        $action = (string) $this->request->getPost('action');
        try {
            $r = (new AnomalieService())->traiter($id, $action, $this->utilisateur['id'], (string) $this->request->getPost('commentaire'));
        } catch (AnomalieException $e) {
            return redirect()->to(site_url('anomalies/' . $id))->with('erreur', $e->getMessage());
        }

        return redirect()->to(site_url('anomalies/' . $id))->with('succes', sprintf(
            'Anomalie %s : %s. Score %s (%s) → %s (%s). %s',
            $r['regle'], ui_libelle($r['vers']), ui_nombre($r['score_avant']), ui_libelle($r['niveau_avant']), ui_nombre($r['score_apres']), ui_libelle($r['niveau_apres']),
            $r['validation_bloquee'] ? $r['bloquantes_ouvertes'] . ' anomalie(s) bloquante(s) encore ouverte(s) : validation bloquée.' : 'Plus aucune anomalie bloquante : la validation est possible.'
        ));
    }

    /** Rejoue les règles sur la période (après correction des écritures) puis recalcule le score. */
    public function recalculer()
    {
        $ctx = $this->contexte();
        if ($ctx['periode'] === null) {
            return redirect()->to(site_url('anomalies'))->with('erreur', 'Aucune période sélectionnée.');
        }
        $s = (int) $ctx['societe']['id'];
        $p = (int) $ctx['periode']['id'];

        $resume = (new RuleEngine())->executer($s, $p);
        $score  = (new CalculScore())->calculer($s, $p, true);
        (new AuditService())->journaliser($this->utilisateur['id'], 'recalcul_anomalies', 'periodes_fiscales', $p, null, ['score' => (string) $score['score'], 'niveau' => $score['niveau']]);

        return redirect()->to(site_url('anomalies'))->with('succes', sprintf(
            'Règles rejouées : %d anomalie(s) ouverte(s) recalculée(s) ; les anomalies déjà traitées sont conservées. Score %s / 100 (%s).', array_sum(array_column($resume, 'nb')), ui_nombre($score['score']), ui_libelle($score['niveau'])
        ));
    }
}