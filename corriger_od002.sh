#!/bin/bash
# OD-002 : écriture datée du 30/09/2026, hors de la période d'octobre. On la redate au 01/10/2026 (dans la période).
cd "$(dirname "$0")" 2>/dev/null; [ -f .env ] || cd .. ; [ -f .env ] || { echo "Lance ce script depuis le dossier du projet (celui qui contient .env)."; exit 1; }
MYSQL=${MYSQL:-$(command -v mysql || echo /opt/lampp/bin/mysql)}
lire() { grep -E "^database.default.$1" .env | sed "s/.*=[ ]*//" | tr -d " '\"\r"; }
DB=$(lire database); DBUSER=$(lire username); DBPASS=$(lire password)
ARGS=(-u "$DBUSER"); [ -n "$DBPASS" ] && ARGS+=("-p$DBPASS")
"$MYSQL" "${ARGS[@]}" "$DB" -e "
UPDATE ecritures SET date_ecriture='2026-10-01' WHERE societe_id=1 AND numero_piece='OD-002' AND date_ecriture='2026-09-30';
SELECT numero_piece, date_ecriture FROM ecritures WHERE societe_id=1 AND numero_piece='OD-002';"
echo "Attendu : OD-002 avec la date 2026-10-01. Dans l'application : Anomalies, OD-002, « Marquer corrigée »."