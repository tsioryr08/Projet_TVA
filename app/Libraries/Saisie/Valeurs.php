<?php

namespace App\Libraries\Saisie;

/** Lecture et validation des valeurs saisies dans les formulaires (montants, dates). */
class Valeurs
{
    /**
     * Montant saisi à la main : « 600000 », « 600 000 », « 600 000,50 ». Renvoie une chaîne décimale (jamais un flottant).
     */
    public static function montant(string $brut, string $libelle, bool $strictementPositif = true): string
    {
        $v = str_replace(["\u{202F}", "\u{00A0}", ' '], '', trim($brut));
        $v = str_replace(',', '.', $v);
        if ($v === '' || ! preg_match('/^\d{1,13}(\.\d{1,2})?$/', $v)) {
            throw new SaisieException($libelle . ' : saisir un montant en Ariary (chiffres uniquement, 2 décimales au plus).');
        }
        if ($strictementPositif && (float) $v <= 0) {
            throw new SaisieException($libelle . ' : le montant doit être supérieur à zéro.');
        }

        return number_format((float) $v, 2, '.', '');
    }

    /** Date au format AAAA-MM-JJ, réelle et pas dans le futur. */
    public static function date(string $brut, string $libelle): string
    {
        $d = \DateTime::createFromFormat('!Y-m-d', trim($brut));
        if ($d === false || $d->format('Y-m-d') !== trim($brut)) {
            throw new SaisieException($libelle . ' : date invalide.');
        }
        if ($d > new \DateTime('today')) {
            throw new SaisieException($libelle . ' : la date ne peut pas être dans le futur.');
        }

        return $d->format('Y-m-d');
    }
}
