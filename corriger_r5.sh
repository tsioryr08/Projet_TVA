#!/bin/bash
# À lancer depuis le dossier du projet (celui qui contient le fichier .env) :  ./NOM.sh
cd "$(dirname "$0")" 2>/dev/null; [ -f .env ] || cd .. ; [ -f .env ] || { echo "Lance ce script depuis le dossier du projet (celui qui contient .env)."; exit 1; }
MYSQL=${MYSQL:-$(command -v mysql || echo /opt/lampp/bin/mysql)}
lire() { grep -E "^database.default.$1" .env | sed "s/.*=[ ]*//" | tr -d " '\"\r"; }
DB=$(lire database); DBUSER=$(lire username); DBPASS=$(lire password)
ARGS=(-u "$DBUSER"); [ -n "$DBPASS" ] && ARGS+=("-p$DBPASS")
# R5 : AC-004, retenue à la source 100 000 -> 200 000 (10 % de 2 000 000) ; le fournisseur (401) est crédité d'autant en moins.
"$MYSQL" "${ARGS[@]}" "$DB" -e "
UPDATE lignes_ecriture l JOIN ecritures e ON e.id=l.ecriture_id JOIN plan_comptable p ON p.id=l.compte_id
  SET l.credit=200000 WHERE e.societe_id=1 AND e.numero_piece='AC-004' AND p.numero='447' AND l.credit=100000;
UPDATE lignes_ecriture l JOIN ecritures e ON e.id=l.ecriture_id JOIN plan_comptable p ON p.id=l.compte_id
  SET l.credit=1800000 WHERE e.societe_id=1 AND e.numero_piece='AC-004' AND p.numero='401' AND l.credit=1900000;
SELECT p.numero AS compte, l.debit, l.credit FROM lignes_ecriture l JOIN ecritures e ON e.id=l.ecriture_id JOIN plan_comptable p ON p.id=l.compte_id WHERE e.numero_piece='AC-004' AND e.societe_id=1;"
echo "Attendu : 447 crédit 200000 et 401 crédit 1800000. Dans l'application : Anomalies, R5, « Marquer corrigée »."