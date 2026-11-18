#!/bin/bash
# À lancer depuis le dossier du projet (celui qui contient le fichier .env) :  ./NOM.sh
cd "$(dirname "$0")" 2>/dev/null; [ -f .env ] || cd .. ; [ -f .env ] || { echo "Lance ce script depuis le dossier du projet (celui qui contient .env)."; exit 1; }
MYSQL=${MYSQL:-$(command -v mysql || echo /opt/lampp/bin/mysql)}
lire() { grep -E "^database.default.$1" .env | sed "s/.*=[ ]*//" | tr -d " '\"\r"; }
DB=$(lire database); DBUSER=$(lire username); DBPASS=$(lire password)
ARGS=(-u "$DBUSER"); [ -n "$DBPASS" ] && ARGS+=("-p$DBPASS")
# R2 : AC-002, TVA déduite sans justificatif. On rattache un document à l'écriture et on renseigne un NIF valide pour le fournisseur Gamma.
"$MYSQL" "${ARGS[@]}" "$DB" -e "
UPDATE tiers SET nif='5000000055', nif_valide=1 WHERE societe_id=1 AND raison_sociale='Gamma Services';
INSERT INTO documents (societe_id, periode_id, tiers_id, type_document, nom_fichier, chemin, hash_sha256, statut_extraction, created_at, updated_at)
  SELECT 1, e.periode_id, e.tiers_id, 'facture', 'facture_gamma_002.pdf', 'demo/facture_gamma_002.pdf', SHA2('facture_gamma_002', 256), 'extrait', NOW(), NOW()
  FROM ecritures e WHERE e.societe_id=1 AND e.numero_piece='AC-002' AND e.document_id IS NULL;
UPDATE ecritures e SET e.document_id=(SELECT MAX(id) FROM documents WHERE nom_fichier='facture_gamma_002.pdf' AND societe_id=1)
  WHERE e.societe_id=1 AND e.numero_piece='AC-002' AND e.document_id IS NULL;
SELECT e.numero_piece, e.document_id, t.nif, t.nif_valide FROM ecritures e JOIN tiers t ON t.id=e.tiers_id WHERE e.societe_id=1 AND e.numero_piece='AC-002';"
echo "Attendu : AC-002 avec un document_id rempli et nif_valide = 1. Dans l'application : Anomalies, R2 AC-002, « Marquer corrigée »."