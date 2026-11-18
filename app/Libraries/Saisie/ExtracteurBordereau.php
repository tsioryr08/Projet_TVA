<?php

namespace App\Libraries\Saisie;

/**
 * Lit le texte d'un PDF de bordereau et en tire trois valeurs : montant TTC, date du document, NIF de l'émetteur.
 *
 * Deux méthodes, essayées dans l'ordre :
 *  1. l'outil « pdftotext » (poppler) s'il est installé sur la machine : il lit presque tous les PDF ;
 *  2. un lecteur interne, sans aucune dépendance, qui comprend les PDF simples (texte non chiffré,
 *     compressé ou non), comme ceux des documents de démonstration.
 * Un PDF scanné (image) ou dont le texte est codé de façon opaque ne donne rien : l'appelant retombe
 * alors sur la saisie à la main. Rien n'est jamais deviné : une valeur absente ou invalide reste null.
 */
class ExtracteurBordereau
{
    public function __construct(private readonly bool $utiliserPdftotext = true)
    {
    }

    /**
     * @return array{montant:?string,date:?string,nif:?string,trouves:int,texte_lu:bool,source:string}
     */
    public function extraire(string $cheminPdf): array
    {
        $vide = ['montant' => null, 'date' => null, 'nif' => null, 'trouves' => 0, 'texte_lu' => false, 'source' => 'aucune'];
        if (! is_file($cheminPdf) || ! is_readable($cheminPdf)) {
            return $vide;
        }

        $texte  = '';
        $source = 'aucune';
        if ($this->utiliserPdftotext) {
            $texte = $this->viaPdftotext($cheminPdf);
            if (trim($texte) !== '') {
                $source = 'pdftotext';
            }
        }
        if (trim($texte) === '') {
            $texte = $this->lecteurInterne($cheminPdf);
            if (trim($texte) !== '') {
                $source = 'interne';
            }
        }
        if (trim($texte) === '') {
            return $vide;
        }

        $r = $this->chercher($texte);
        $r['trouves']  = count(array_filter([$r['montant'], $r['date'], $r['nif']], static fn ($v) => $v !== null));
        $r['texte_lu'] = true;
        $r['source']   = $source;

        return $r;
    }

    /** Cherche les trois libellés dans le texte lu. @return array{montant:?string,date:?string,nif:?string} */
    public function chercher(string $texte): array
    {
        $texte = str_replace(["\u{00A0}", "\u{202F}"], ' ', $texte);

        // NIF : « NIF émetteur : 5000000044 » (variantes : « NIF emetteur », « NIF »).
        $nif = null;
        if (preg_match('/\bNIF(?:[ \t]+[ée]metteur)?[ \t]*[:\-][ \t]*([0-9A-Z]{5,30})\b/iu', $texte, $m)) {
            $nif = strtoupper($m[1]);
        }

        // Date : « Date du document : 31/10/2026 » (ou 31-10-2026, ou 2026-10-31).
        $date = null;
        if (preg_match('/\bDate(?:[ \t]+du[ \t]+document)?[ \t]*:[ \t]*(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})\b/iu', $texte, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            $date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        } elseif (preg_match('/\bDate(?:[ \t]+du[ \t]+document)?[ \t]*:[ \t]*(\d{4})-(\d{2})-(\d{2})\b/iu', $texte, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $date = $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if ($date !== null) {
            try {
                $date = Valeurs::date($date, 'Date du document'); // réelle, pas dans le futur : même contrôle que la saisie
            } catch (SaisieException) {
                $date = null;
            }
        }

        // Montant : « Montant TTC : 1 250 000 Ar » (espaces horizontaux seulement : on ne déborde pas sur la ligne suivante).
        $montant = null;
        if (preg_match('/\bMontant(?:[ \t]+TTC)?[ \t]*:?[ \t]*([0-9](?:[0-9 ]*[0-9])?(?:[.,][0-9]{1,2})?)/iu', $texte, $m)) {
            try {
                $montant = Valeurs::montant($m[1], 'Montant TTC');
            } catch (SaisieException) {
                $montant = null;
            }
        }

        return ['montant' => $montant, 'date' => $date, 'nif' => $nif];
    }

    // ---------------------------------------------------------------------------------------------
    // Méthode 1 : pdftotext (aucune chaîne venant de l'utilisateur dans la commande : tableau d'arguments)
    // ---------------------------------------------------------------------------------------------
    private function viaPdftotext(string $chemin): string
    {
        if (! function_exists('proc_open')) {
            return '';
        }
        $binaire = null;
        foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext', '/opt/homebrew/bin/pdftotext'] as $c) {
            if (is_executable($c)) {
                $binaire = $c;
                break;
            }
        }
        if ($binaire === null) {
            return '';
        }

        $proc = @proc_open([$binaire, '-enc', 'UTF-8', '-l', '3', $chemin, '-'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($proc)) {
            return '';
        }
        stream_set_blocking($pipes[1], true);
        $sortie = (string) stream_get_contents($pipes[1], 200000);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        return $sortie;
    }

    // ---------------------------------------------------------------------------------------------
    // Méthode 2 : lecteur interne des PDF simples
    // ---------------------------------------------------------------------------------------------
    private function lecteurInterne(string $chemin): string
    {
        $brut = (string) file_get_contents($chemin, false, null, 0, 5 * 1024 * 1024);
        if ($brut === '') {
            return '';
        }

        $texte = '';
        if (preg_match_all('/<<(.*?)>>\s*stream\r?\n(.*?)\r?\nendstream/s', $brut, $blocs, PREG_SET_ORDER)) {
            foreach ($blocs as $b) {
                $flux = $b[2];
                if (stripos($b[1], 'FlateDecode') !== false) {
                    $dec = @gzuncompress($flux);
                    if ($dec === false) {
                        $dec = @gzinflate(substr($flux, 2));
                    }
                    if ($dec === false) {
                        continue;
                    }
                    $flux = $dec;
                } elseif (stripos($b[1], '/Filter') !== false) {
                    continue; // autre filtre (image, etc.) : on ne sait pas le lire
                }
                if (! str_contains($flux, 'Tj') && ! str_contains($flux, 'TJ')) {
                    continue;
                }
                $texte .= $this->texteDuFlux($flux) . "\n";
            }
        }

        // Texte codé avec une police à tables de caractères (PDF produits par un navigateur ou un traitement de texte) :
        // les « lettres » sont des numéros de glyphes, illisibles ici. On ne garde que du texte qui ressemble à des mots.
        return preg_match('/\p{L}{3,}/u', $texte) ? $texte : '';
    }

    /** Extrait le texte des opérateurs Tj, TJ, ' et " d'un flux de contenu, ligne par ligne. */
    private function texteDuFlux(string $flux): string
    {
        $sortie = '';
        $n      = strlen($flux);
        $i      = 0;
        $pile   = []; // opérandes en attente (chaînes décodées, nombres, tableaux)
        while ($i < $n) {
            $c = $flux[$i];
            if ($c === '(') {
                [$chaine, $i] = $this->chaineLitterale($flux, $i);
                $pile[]       = $chaine;
            } elseif ($c === '<' && ($flux[$i + 1] ?? '') !== '<') {
                $fin = strpos($flux, '>', $i);
                if ($fin === false) {
                    break;
                }
                $hex    = preg_replace('/[^0-9A-Fa-f]/', '', substr($flux, $i + 1, $fin - $i - 1)) ?? '';
                $pile[] = $this->decoder((string) hex2bin(strlen($hex) % 2 ? $hex . '0' : $hex));
                $i      = $fin + 1;
            } elseif ($c === '[') {
                $pile[] = '[';
                $i++;
            } elseif ($c === ']') {
                $morceaux = [];
                while ($pile !== [] && ($dernier = array_pop($pile)) !== '[') {
                    array_unshift($morceaux, $dernier);
                }
                $ligne = '';
                foreach ($morceaux as $m) {
                    $ligne .= is_float($m) ? ($m < -200 ? ' ' : '') : $m;
                }
                $pile[] = $ligne;
                $i++;
            } elseif (preg_match('/\G[A-Za-z\'"*]+/', $flux, $m, 0, $i) === 1) {
                $op = $m[0];
                $i += strlen($op);
                switch ($op) {
                    case 'Tj':
                    case 'TJ':
                    case "'":
                    case '"':
                        $texteOp = '';
                        foreach ($pile as $p) {
                            if (is_string($p) && $p !== '[') {
                                $texteOp .= $p;
                            }
                        }
                        $sortie .= $texteOp . ($op === 'Tj' || $op === 'TJ' ? '' : "\n");
                        $pile = [];
                        break;
                    case 'Td':
                    case 'TD':
                        $ty = end($pile);
                        $sortie .= (is_float($ty) && abs($ty) > 0.01) ? "\n" : ' ';
                        $pile = [];
                        break;
                    case 'T*':
                    case 'Tm':
                    case 'ET':
                        $sortie .= "\n";
                        $pile = [];
                        break;
                    default:
                        $pile = [];
                }
            } elseif (preg_match('/\G-?\d*\.?\d+/', $flux, $m, 0, $i) === 1) {
                $pile[] = (float) $m[0];
                $i += strlen($m[0]);
            } else {
                $i++;
            }
        }

        return $sortie;
    }

    /** @return array{0:string,1:int} chaîne littérale PDF « ( ... ) » décodée, et la position qui suit. */
    private function chaineLitterale(string $s, int $i): array
    {
        $n = strlen($s);
        $i++;
        $niveau = 1;
        $brut   = '';
        while ($i < $n && $niveau > 0) {
            $c = $s[$i];
            if ($c === '\\') {
                $suite = $s[$i + 1] ?? '';
                if (ctype_digit($suite)) {
                    preg_match('/\G[0-7]{1,3}/', $s, $o, 0, $i + 1);
                    $brut .= chr(octdec($o[0] ?? '0') & 0xFF);
                    $i += 1 + strlen($o[0] ?? '0');
                    continue;
                }
                $brut .= match ($suite) {
                    'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C",
                    "\n", "\r" => '',
                    default => $suite,
                };
                $i += 2;
                continue;
            }
            if ($c === '(') {
                $niveau++;
            } elseif ($c === ')') {
                $niveau--;
                if ($niveau === 0) {
                    $i++;
                    break;
                }
            }
            $brut .= $c;
            $i++;
        }

        return [$this->decoder($brut), $i];
    }

    /** Octets d'une chaîne PDF vers UTF-8 : UTF-16 si elle commence par la marque FE FF, sinon Windows-1252. */
    private function decoder(string $octets): string
    {
        if (str_starts_with($octets, "\xFE\xFF")) {
            return (string) mb_convert_encoding(substr($octets, 2), 'UTF-8', 'UTF-16BE');
        }

        return (string) mb_convert_encoding($octets, 'UTF-8', 'Windows-1252');
    }
}