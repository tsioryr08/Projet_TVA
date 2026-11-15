#!/bin/bash
# Usage : ./demo_preparer.sh figer   (une seule fois, sur une base propre : enregistre l'état de référence)
#         ./demo_preparer.sh         (avant chaque répétition : restaure l'état de référence)
cd "$(dirname "$0")" || exit 1
PHP=${PHP:-/opt/lampp/bin/php}
MYSQL=${MYSQL:-/opt/lampp/bin/mysql}
DUMP=${DUMP:-/opt/lampp/bin/mysqldump}
lire() { grep -E "^database.default.$1" .env | sed "s/.*=[ ]*//" | tr -d " '\"\r"; }
DB=$(lire database); DBUSER=$(lire username); DBPASS=$(lire password)
ARGS=(-u "$DBUSER"); [ -n "$DBPASS" ] && ARGS+=("-p$DBPASS")
REF=writable/base_propre.sql

if [ "$1" = "figer" ]; then
  $PHP spark demo:reinitialiser | tail -n 8
  echo
  echo "Vérifie : société 1 = 85 / 100 et société 2 = 0 / 100."
  echo "Si c'est bon, appuie sur ENTRÉE pour figer cet état. Sinon Ctrl+C et recrée la base."
  read -r
  "$DUMP" "${ARGS[@]}" "$DB" > "$REF" && echo "État de référence enregistré : $REF ($(wc -c < "$REF") octets)"
  exit 0
fi

[ -f "$REF" ] || { echo "Pas d'état de référence. Lance d'abord : ./demo_preparer.sh figer"; exit 1; }
"$MYSQL" "${ARGS[@]}" "$DB" < "$REF" || { echo "Restauration en erreur"; exit 1; }
rm -rf writable/uploads/documents/*
$PHP spark demo:reinitialiser | tail -n 8
echo "Démo prête."
