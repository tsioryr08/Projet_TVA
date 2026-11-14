<?php

namespace App\Commands;

use App\Libraries\Parametrage\ParametrageException;
use App\Libraries\Parametrage\ParametrageRegles;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Liste ou modifie les règles. Exemples :
 *   php spark regles:parametrer                                          liste des règles
 *   php spark regles:parametrer R2 poids=3 --apercu                      simule (rien n'est enregistré)
 *   php spark regles:parametrer R2 poids=3 --motif "Risque accru"        applique
 *   php spark regles:parametrer R1 tolerance_absolue=500 --motif "..."   change un seuil
 *   php spark regles:parametrer R6 active=0 --motif "..."                désactive une règle
 * Champs : poids, gravite, active, ou tout seuil numérique/booléen de la règle. Option --par : e-mail de l'utilisateur.
 */
class ParametrerRegles extends BaseCommand
{
    protected $group       = 'Fiscal';
    protected $name        = 'regles:parametrer';
    protected $description = 'Liste ou modifie les règles de contrôle (poids, gravité, activation, seuils), avec recalcul du score.';
    protected $usage       = 'regles:parametrer [code] [champ=valeur ...] [--motif "texte"] [--apercu] [--par email]';

    public function run(array $params)
    {
        $db      = Database::connect();
        $service = new ParametrageRegles();

        if (array_filter(array_keys($params), 'is_int') === []) {
            CLI::table(array_map(fn ($r) => [
                $r['code'], $r['active'] ? 'oui' : 'NON', $r['gravite'], (string) $r['poids'],
                implode(', ', array_map(fn ($k, $v) => $k . '=' . (is_bool($v) ? ($v ? 'oui' : 'non') : (is_string($v) ? $v : $v)), array_keys($r['parametres']), $r['parametres'])),
            ], $service->lister()), ['Règle', 'Active', 'Gravité', 'Poids', 'Paramètres']);

            return EXIT_SUCCESS;
        }

        $code    = array_shift($params);
        $regle   = $db->table('regles_controle')->where('code', strtoupper($code))->get()->getRowArray();
        $societe = $db->table('societes')->get()->getRowArray();
        $periode = $db->table('periodes_fiscales')->where(['annee' => 2026, 'mois' => 10])->get()->getRowArray();
        if ($regle === null || $societe === null || $periode === null) {
            CLI::error('Règle, société ou période introuvable.');

            return EXIT_ERROR;
        }

        // CodeIgniter mélange options (clés texte) et arguments (clés numériques) : on ne garde que les arguments.
        $modifs = [];
        foreach ($params as $cleParam => $p) {
            if (! is_int($cleParam)) {
                continue;
            }
            if (! str_contains((string) $p, '=')) {
                CLI::error('Argument invalide : ' . $p . ' (attendu : champ=valeur).');

                return EXIT_ERROR;
            }
            [$cle, $valeur] = explode('=', $p, 2);
            if (in_array($cle, ['poids', 'gravite', 'active'], true)) {
                $modifs[$cle] = $valeur;
            } else {
                $modifs['parametres'][$cle] = $valeur;
            }
        }

        try {
            if (CLI::getOption('apercu') !== null) {
                $res = $service->previsualiser((int) $regle['id'], $modifs, (int) $societe['id'], (int) $periode['id']);
            } else {
                $email = (string) (CLI::getOption('par') ?: 'fiscal@demo.mg');
                $user  = $db->table('utilisateurs')->where('email', $email)->get()->getRowArray();
                if ($user === null) {
                    CLI::error('Utilisateur introuvable : ' . $email);

                    return EXIT_ERROR;
                }
                $res = $service->appliquer((int) $regle['id'], $modifs, (string) CLI::getOption('motif'), (int) $user['id'], (int) $societe['id'], (int) $periode['id']);
            }
        } catch (ParametrageException $e) {
            CLI::write('[REFUSÉ] ' . $e->getMessage(), 'red');

            return EXIT_ERROR;
        }

        CLI::write(($res['applique'] ? '[APPLIQUÉ] ' : '[APERÇU, rien n\'est enregistré] ') . 'Règle ' . $res['regle'], $res['applique'] ? 'green' : 'yellow');
        foreach ($res['modifications'] as $champ => $m) {
            if ($champ === 'parametres') {
                foreach ($m as $k => $v) {
                    CLI::write(sprintf('  %s : %s -> %s', $k, json_encode($v['avant']), json_encode($v['apres'])));
                }
            } else {
                CLI::write(sprintf('  %s : %s -> %s', $champ, json_encode($m['avant']), json_encode($m['apres'])));
            }
        }
        CLI::write(sprintf('  Score : %s (%s) -> %s (%s), variation %+.1f', $res['score_avant'], $res['niveau_avant'], $res['score_apres'], $res['niveau_apres'], $res['variation']));
        CLI::write(sprintf('  Anomalies : %d -> %d ; bloquantes ouvertes : %d -> %d%s', $res['anomalies_avant'], $res['anomalies_apres'], $res['bloquantes_avant'], $res['bloquantes_apres'], $res['validation_bloquee_apres'] ? ' (validation bloquée)' : ' (validation possible)'));

        return EXIT_SUCCESS;
    }
}
