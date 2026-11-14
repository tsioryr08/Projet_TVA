<?php

/**
 * Aides d'affichage pour les vues (montants, dates, pastilles de couleur).
 */

if (! function_exists('ui_ar')) {
    function ui_ar(float|int|string|null $v): string
    {
        return number_format((float) $v, 0, ',', "\u{202F}") . ' Ar';
    }
}

if (! function_exists('ui_nombre')) {
    function ui_nombre(float|int|string|null $v, int $decimales = 1): string
    {
        $f = (float) $v;

        return number_format($f, floor($f) == $f ? 0 : $decimales, ',', ' ');
    }
}

if (! function_exists('ui_date')) {
    function ui_date(?string $d, bool $heure = false): string
    {
        if ($d === null || $d === '') {
            return '-';
        }

        return date($heure ? 'd/m/Y H:i' : 'd/m/Y', strtotime($d));
    }
}

if (! function_exists('ui_libelle')) {
    function ui_libelle(string $valeur): string
    {
        static $m = [
            'elevee' => 'élevée', 'justifiee' => 'justifiée', 'corrigee' => 'corrigée', 'ignoree' => 'ignorée',
            'a_valider' => 'à valider', 'a_corriger' => 'à corriger', 'validee' => 'validée', 'deposee' => 'déposée', 'archivee' => 'archivée',
            'eleve' => 'élevé', 'responsable_fiscal' => 'responsable fiscal', 'revue_comptable' => 'revue comptable',
            'validation_responsable' => 'validation responsable', 'cloturee' => 'clôturée', 'declaree' => 'déclarée',
        ];

        return $m[$valeur] ?? $valeur;
    }
}

if (! function_exists('ui_badge')) {
    /** Pastille colorée : type = gravite, statut, niveau, decl, periode. */
    function ui_badge(string $type, string $valeur): string
    {
        return '<span class="badge ' . esc($type . '-' . $valeur, 'attr') . '">' . esc(ui_libelle($valeur)) . '</span>';
    }
}
