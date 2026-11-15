#!/bin/bash
cd "$(dirname "$0")" || exit 1
PHP=${PHP:-/opt/lampp/bin/php}
MYSQL=${MYSQL:-/opt/lampp/bin/mysql}
lire() { grep -E "^database.default.$1" .env | sed "s/.*=[ ]*//" | tr -d " '\"\r"; }
DB=$(lire database); DBUSER=$(lire username); DBPASS=$(lire password)
ARGS=(-u "$DBUSER"); [ -n "$DBPASS" ] && ARGS+=("-p$DBPASS")

sql() { "$MYSQL" "${ARGS[@]}" "$DB" -e "$1"; }

essai() { # $1 nom, $2 attendu, $3 sql, $4 règle
  echo; echo "=================================================="
  echo "TEST $1"; echo "ATTENDU : $2"
  "$MYSQL" "${ARGS[@]}" "$DB" < writable/base_propre.sql
  sql "$3" || echo "!! SQL EN ERREUR"
  $PHP spark regles:executer 2026 10 >/dev/null 2>&1
  echo "--- RÉSULTAT (anomalies $4) :"
  $PHP spark anomalies:liste 2026 10 --regle "$4" 2>&1 | grep -v '^\s*$'
}

BUL="UPDATE bulletins_paie b JOIN employes e ON e.id=b.employe_id SET"
E3="WHERE e.societe_id=1 AND e.matricule='E003';"
LIG="UPDATE lignes_ecriture l JOIN ecritures e ON e.id=l.ecriture_id JOIN plan_comptable p ON p.id=l.compte_id SET"
OD3="e.societe_id=1 AND e.numero_piece='OD-003'"
AC4="e.societe_id=1 AND e.numero_piece='AC-004'"
ID601=$("$MYSQL" "${ARGS[@]}" -N -B "$DB" -e "SELECT id FROM plan_comptable WHERE numero='601'")

# ---------------- R4 : IRSA recalculé (bulletin E003 Rakoto, base 588 000) ----------------
essai "R4-0 situation de départ" "1 anomalie : E003, 20 000 retenu au lieu de 25 700" "SELECT 1;" R4
essai "R4-1 IRSA corrigé" "0 anomalie" "$BUL b.irsa_retenu=25700 $E3" R4
essai "R4-2 écart de 100 (limite de tolérance)" "0 anomalie" "$BUL b.irsa_retenu=25800 $E3" R4
essai "R4-3 écart de 101" "1 anomalie, écart 101" "$BUL b.irsa_retenu=25801 $E3" R4
essai "R4-4 base non arrondie 588 099" "0 anomalie (base arrondie à 588 000)" "$BUL b.base_imposable=588099, b.irsa_retenu=25700 $E3" R4
essai "R4-5 deux personnes à charge" "1 anomalie : attendu 21 700, écart 4 000" "UPDATE employes SET nb_charges=2 WHERE societe_id=1 AND matricule='E003'; $BUL b.irsa_retenu=25700 $E3" R4
essai "R4-6 base 300 000 et IRSA à 0" "1 anomalie : attendu 3 000 (minimum de perception)" "$BUL b.base_imposable=300000, b.irsa_retenu=0 $E3" R4
essai "R4-7 base 4 500 000, IRSA au barème 2025" "1 anomalie : attendu 832 500 (barème 2026 NON VÉRIFIÉ), écart 25 000" "$BUL b.base_imposable=4500000, b.irsa_retenu=807500 $E3" R4

# ---------------- R3 : masse salariale (compte 641 = 1 750 000, bulletins = 1 800 000) ----------------
essai "R3-0 situation de départ" "1 anomalie, écart 50 000" "SELECT 1;" R3
essai "R3-1 compta alignée sur les bulletins" "0 anomalie" "$LIG l.debit=1800000 WHERE $OD3 AND p.numero='641'; $LIG l.credit=1800000 WHERE $OD3 AND p.numero='421';" R3
essai "R3-2 écart de 1 000 (limite)" "0 anomalie" "$LIG l.debit=1799000 WHERE $OD3 AND p.numero='641'; $LIG l.credit=1799000 WHERE $OD3 AND p.numero='421';" R3
essai "R3-3 écart de 1 001" "1 anomalie, écart 1 001" "$LIG l.debit=1798999 WHERE $OD3 AND p.numero='641'; $LIG l.credit=1798999 WHERE $OD3 AND p.numero='421';" R3
essai "R3-4 bulletins à 0" "1 anomalie, écart 1 750 000" "UPDATE bulletins_paie b JOIN employes e ON e.id=b.employe_id SET b.salaire_brut=0 WHERE e.societe_id=1;" R3

# ---------------- R5 : retenue non-résident (AC-004 : 622 = 2 000 000, retenue 100 000) ----------------
essai "R5-0 situation de départ" "1 anomalie : retenue 100 000 au lieu de 200 000" "SELECT 1;" R5
essai "R5-1 retenue correcte" "0 anomalie" "$LIG l.credit=200000 WHERE $AC4 AND p.numero='447'; $LIG l.credit=1800000 WHERE $AC4 AND p.numero='401';" R5
essai "R5-2 aucune retenue" "1 anomalie : 0 au lieu de 200 000" "$LIG l.credit=0 WHERE $AC4 AND p.numero='447'; $LIG l.credit=2000000 WHERE $AC4 AND p.numero='401';" R5
essai "R5-3 écart de 100 (limite)" "0 anomalie" "$LIG l.credit=199900 WHERE $AC4 AND p.numero='447'; $LIG l.credit=1800100 WHERE $AC4 AND p.numero='401';" R5
essai "R5-4 écart de 101" "1 anomalie, écart 101" "$LIG l.credit=199899 WHERE $AC4 AND p.numero='447'; $LIG l.credit=1800101 WHERE $AC4 AND p.numero='401';" R5
essai "R5-5 tiers déclaré résident" "0 anomalie (LIMITE : les résidents ne sont pas contrôlés)" "UPDATE tiers SET non_resident=0 WHERE societe_id=1 AND raison_sociale LIKE 'Consulting%';" R5
essai "R5-6 charge sur le compte 601 au lieu de 622" "0 anomalie (LIMITE : seul le compte 622 est contrôlé)" "$LIG l.compte_id=$ID601 WHERE $AC4 AND p.numero='622';" R5

echo; echo "=================================================="
echo "Retour à la démo propre :"
"$MYSQL" "${ARGS[@]}" "$DB" < writable/base_propre.sql
"$MYSQL" "${ARGS[@]}" "$DB" < writable/base_propre.sql
$PHP spark demo:reinitialiser | tail -n 8
