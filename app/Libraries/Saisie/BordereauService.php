<?php

namespace App\Libraries\Saisie;

use App\Libraries\Workflow\AuditService;
use App\Models\DocumentModel;
use App\Models\DonneeExtraiteModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Database;

/**
 * Saisie assistée des bordereaux : le PDF est joint, et trois champs lus à la main sont enregistrés
 * (montant_ttc, date_document, nif_emetteur) dans documents + donnees_extraites.
 * Pas d'extraction automatique du PDF : choix assumé pour cette version (confiance = 100, saisie humaine).
 *
 * Les fichiers sont rangés hors de public/ : writable/uploads/documents/{société}/{nom aléatoire}.pdf
 */
class BordereauService
{
    public const TYPES      = ['bordereau', 'facture', 'bulletin', 'autre'];
    public const TAILLE_MAX = 5 * 1024 * 1024;
    public const DOSSIER    = 'writable/uploads/documents';

    /**
     * @param array<string,mixed> $saisie type_document, tiers_id, montant_ttc, date_document, nif_emetteur
     *
     * @return array{id:int,avertissement:?string}
     */
    public function creer(int $societeId, ?int $periodeId, array $saisie, ?UploadedFile $fichier, int $utilisateurId): array
    {
        $db = Database::connect();

        // ---- Validation (rien n'est écrit avant que tout soit valide) ---------------------------
        $type = (string) ($saisie['type_document'] ?? '');
        if (! in_array($type, self::TYPES, true)) {
            throw new SaisieException('Type de document invalide.');
        }
        $tiersId = (int) ($saisie['tiers_id'] ?? 0);
        if ($tiersId > 0 && $db->table('tiers')->where(['id' => $tiersId, 'societe_id' => $societeId])->countAllResults() === 0) {
            throw new SaisieException('Ce tiers n\'appartient pas à la société sélectionnée.');
        }
        $montant = Valeurs::montant((string) ($saisie['montant_ttc'] ?? ''), 'Montant TTC');
        $date    = Valeurs::date((string) ($saisie['date_document'] ?? ''), 'Date du document');
        $nif     = strtoupper(str_replace(' ', '', trim((string) ($saisie['nif_emetteur'] ?? ''))));
        if (! preg_match('/^[0-9A-Z]{5,30}$/', $nif)) {
            throw new SaisieException('NIF de l\'émetteur : 5 à 30 chiffres ou lettres, sans signe.');
        }
        $hash = $this->controlerFichier($fichier);
        if ($db->table('documents')->where(['societe_id' => $societeId, 'hash_sha256' => $hash])->countAllResults() > 0) {
            throw new SaisieException('Ce PDF a déjà été enregistré pour cette société (même contenu).');
        }

        // ---- Rangement du fichier ----------------------------------------------------------------
        $dossier = ROOTPATH . self::DOSSIER . '/' . $societeId;
        if (! is_dir($dossier) && ! mkdir($dossier, 0775, true) && ! is_dir($dossier)) {
            throw new SaisieException('Impossible de créer le dossier de stockage.');
        }
        $nomStocke = bin2hex(random_bytes(16)) . '.pdf';
        $fichier->move($dossier, $nomStocke);
        $nomAffiche = mb_substr(preg_replace('/[^\p{L}\p{N}._ \-]/u', '_', basename($fichier->getClientName())), 0, 200);

        // ---- Enregistrement (une transaction : le document et ses trois champs, ou rien) ----------
        $db->transStart();
        $docs = new DocumentModel();
        $docs->insert([
            'societe_id' => $societeId, 'periode_id' => $periodeId, 'tiers_id' => $tiersId > 0 ? $tiersId : null,
            'type_document' => $type, 'nom_fichier' => $nomAffiche, 'chemin' => self::DOSSIER . '/' . $societeId . '/' . $nomStocke,
            'hash_sha256' => $hash, 'statut_extraction' => 'extrait',
        ]);
        $docId   = (int) $docs->getInsertID();
        $donnees = new DonneeExtraiteModel();
        foreach (['montant_ttc' => $montant, 'date_document' => $date, 'nif_emetteur' => $nif] as $champ => $valeur) {
            $donnees->insert(['document_id' => $docId, 'champ' => $champ, 'valeur' => $valeur, 'confiance' => 100]);
        }
        $db->transComplete();

        if (! $db->transStatus() || $docId === 0) {
            @unlink($dossier . '/' . $nomStocke);

            throw new SaisieException('Enregistrement impossible : rien n\'a été sauvegardé.');
        }

        (new AuditService())->journaliser($utilisateurId, 'saisie_bordereau', 'documents', $docId, null, [
            'type_document' => $type, 'montant_ttc' => $montant, 'date_document' => $date, 'nif_emetteur' => $nif, 'hash' => substr($hash, 0, 16),
        ]);

        return ['id' => $docId, 'avertissement' => $this->avertissementPeriode($periodeId, $date)];
    }

    /**
     * Documents saisis pour une société (et une période si elle est donnée), avec leurs trois champs.
     *
     * @return list<array<string,mixed>>
     */
    public function lister(int $societeId, ?int $periodeId = null): array
    {
        $db = Database::connect();
        $q  = $db->table('documents d')->select('d.*, t.raison_sociale AS tiers')
            ->join('tiers t', 't.id = d.tiers_id', 'left')->where('d.societe_id', $societeId);
        if ($periodeId !== null) {
            $q->where('d.periode_id', $periodeId);
        }
        $docs = $q->orderBy('d.id', 'DESC')->get()->getResultArray();
        if ($docs === []) {
            return [];
        }
        $champs = [];
        $lignes = $db->table('donnees_extraites')->whereIn('document_id', array_column($docs, 'id'))->get()->getResultArray();
        foreach ($lignes as $l) {
            $champs[(int) $l['document_id']][$l['champ']] = $l['valeur'];
        }
        foreach ($docs as &$d) {
            $d['champs'] = $champs[(int) $d['id']] ?? [];
        }

        return $docs;
    }

    /** @return list<array<string,mixed>> tiers de la société, pour le menu déroulant */
    public function tiers(int $societeId): array
    {
        return Database::connect()->table('tiers')->select('id, raison_sociale, nif')->where('societe_id', $societeId)->orderBy('raison_sociale')->get()->getResultArray();
    }

    /** @return ?array{chemin:string,nom:string} chemin absolu du PDF, seulement s'il est bien dans le dossier de stockage */
    public function fichier(int $documentId): ?array
    {
        $d = Database::connect()->table('documents')->where('id', $documentId)->get()->getRowArray();
        if ($d === null) {
            return null;
        }
        $abs  = realpath(ROOTPATH . $d['chemin']);
        $base = realpath(ROOTPATH . self::DOSSIER);
        if ($abs === false || $base === false || ! str_starts_with($abs, $base . DIRECTORY_SEPARATOR)) {
            return null; // fichier absent (ex. documents de démonstration) ou chemin suspect
        }

        return ['chemin' => $abs, 'nom' => (string) $d['nom_fichier']];
    }

    /** Vérifie que c'est un vrai PDF de taille raisonnable ; renvoie son SHA-256. */
    private function controlerFichier(?UploadedFile $f): string
    {
        if ($f === null || ! $f->isValid() || $f->hasMoved()) {
            throw new SaisieException('Joindre le PDF du bordereau (le fichier n\'a pas été reçu : à joindre de nouveau).');
        }
        if ($f->getSize() > self::TAILLE_MAX) {
            throw new SaisieException('Le PDF dépasse 5 Mo.');
        }
        if (strtolower($f->getClientExtension()) !== 'pdf') {
            throw new SaisieException('Seuls les fichiers PDF sont acceptés.');
        }
        $tete = (string) file_get_contents($f->getTempName(), false, null, 0, 5);
        if ($tete !== '%PDF-') {
            throw new SaisieException('Ce fichier n\'est pas un PDF valide.');
        }

        return hash_file('sha256', $f->getTempName());
    }

    private function avertissementPeriode(?int $periodeId, string $date): ?string
    {
        if ($periodeId === null) {
            return null;
        }
        $p = Database::connect()->table('periodes_fiscales')->where('id', $periodeId)->get()->getRowArray();
        if ($p !== null && ($date < $p['date_debut'] || $date > $p['date_fin'])) {
            return sprintf('La date du document (%s) est en dehors de la période %02d/%d.', date('d/m/Y', strtotime($date)), $p['mois'], $p['annee']);
        }

        return null;
    }
}
