#!/bin/bash
# À lancer depuis le dossier du projet (celui qui contient le fichier .env) :  ./NOM.sh
cd "$(dirname "$0")" 2>/dev/null; [ -f .env ] || cd .. ; [ -f .env ] || { echo "Lance ce script depuis le dossier du projet (celui qui contient .env)."; exit 1; }
MYSQL=${MYSQL:-$(command -v mysql || echo /opt/lampp/bin/mysql)}
lire() { grep -E "^database.default.$1" .env | sed "s/.*=[ ]*//" | tr -d " '\"\r"; }
DB=$(lire database); DBUSER=$(lire username); DBPASS=$(lire password)
ARGS=(-u "$DBUSER"); [ -n "$DBPASS" ] && ARGS+=("-p$DBPASS")
# R3 : compte 641 comptabilisé 1 750 000 au lieu de 1 800 000 (somme des bruts des bulletins). On corrige 641 et 421.
"$MYSQL" "${ARGS[@]}" "$DB" -e "
UPDATE lignes_ecriture l JOIN ecritures e ON e.id=l.ecriture_id JOIN plan_comptable p ON p.id=l.compte_id
  SET l.debit=1800000 WHERE e.societe_id=1 AND e.numero_piece='OD-003' AND p.numero='641' AND l.debit=1750000;
UPDATE lignes_ecriture l JOIN ecritures e ON e.id=l.ecriture_id JOIN plan_comptable p ON p.id=l.compte_id
  SET l.credit=1800000 WHERE e.societe_id=1 AND e.numero_piece='OD-003' AND p.numero='421' AND l.credit=1750000;
SELECT p.numero AS compte, l.debit, l.credit FROM lignes_ecriture l JOIN ecritures e ON e.id=l.ecriture_id JOIN plan_comptable p ON p.id=l.compte_id WHERE e.numero_piece='OD-003' AND e.societe_id=1;"
echo "Attendu : 641 débit 1800000 et 421 crédit 1800000. Dans l'application : Anomalies, R3, « Marquer corrigée »."