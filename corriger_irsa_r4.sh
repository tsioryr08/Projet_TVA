#!/bin/bash
# À lancer depuis le dossier du projet (celui qui contient le fichier .env) :  ./NOM.sh
cd "$(dirname "$0")" 2>/dev/null; [ -f .env ] || cd .. ; [ -f .env ] || { echo "Lance ce script depuis le dossier du projet (celui qui contient .env)."; exit 1; }
MYSQL=${MYSQL:-$(command -v mysql || echo /opt/lampp/bin/mysql)}
lire() { grep -E "^database.default.$1" .env | sed "s/.*=[ ]*//" | tr -d " '\"\r"; }
DB=$(lire database); DBUSER=$(lire username); DBPASS=$(lire password)
ARGS=(-u "$DBUSER"); [ -n "$DBPASS" ] && ARGS+=("-p$DBPASS")
# R4 : bulletin de Rakoto (E003), IRSA retenu 20 000 -> 25 700 ; le net à payer baisse de la même différence (5 700).
"$MYSQL" "${ARGS[@]}" "$DB" -e "
UPDATE bulletins_paie b JOIN employes m ON m.id=b.employe_id JOIN periodes_fiscales p ON p.id=b.periode_id
  SET b.irsa_retenu=25700, b.net_a_payer=b.net_a_payer-5700
  WHERE m.nom='Rakoto' AND p.societe_id=1 AND p.annee=2026 AND p.mois=10 AND b.irsa_retenu=20000;
SELECT m.nom, b.base_imposable, b.irsa_retenu, b.net_a_payer FROM bulletins_paie b JOIN employes m ON m.id=b.employe_id JOIN periodes_fiscales p ON p.id=b.periode_id WHERE m.nom='Rakoto' AND p.societe_id=1 AND p.mois=10;"
echo "Attendu : irsa_retenu = 25700. Dans l'application : Anomalies, R4, « Marquer corrigée »."