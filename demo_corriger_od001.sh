#!/bin/bash
# Démo, étape 4c : corrige l'écriture déséquilibrée OD-001 (crédit 250 000 -> 300 000).
cd "$(dirname "$0")" || exit 1
MYSQL=${MYSQL:-/opt/lampp/bin/mysql}
lire() { grep -E "^$1" .env | sed "s/.*=[ ]*//" | tr -d " '\"\r"; }
DB=$(lire database.default.database); DBUSER=$(lire database.default.username); DBPASS=$(lire database.default.password)
ARGS=(-u "$DBUSER"); [ -n "$DBPASS" ] && ARGS+=("-p$DBPASS")
"$MYSQL" "${ARGS[@]}" "$DB" -e "UPDATE lignes_ecriture l JOIN ecritures e ON e.id=l.ecriture_id SET l.credit=300000 WHERE e.societe_id=1 AND e.numero_piece='OD-001' AND l.credit=250000; SELECT ROW_COUNT() AS lignes_modifiees;"
echo "Attendu : lignes_modifiees = 1. Dans l'application, ouvrir l'anomalie OD-001 puis cliquer « Corriger »."
