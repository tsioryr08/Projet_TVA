#!/bin/bash
# Recette automatique : pages de chaque rôle, saisie des bordereaux, historique des contrôles.
# À lancer sur la base de référence ; relancer ./demo_preparer.sh ensuite (le script ajoute des données).
cd "$(dirname "$0")" || exit 1
MYSQL=${MYSQL:-/opt/lampp/bin/mysql}
lire() { grep -E "^$1" .env | sed "s/.*=[ ]*//" | tr -d " '\"\r"; }
DB=$(lire database.default.database); DBUSER=$(lire database.default.username); DBPASS=$(lire database.default.password)
BASE=$(lire app.baseURL); BASE=${BASE%/}; [ -z "$BASE" ] && BASE="http://localhost:8080"
ARGS=(-u "$DBUSER"); [ -n "$DBPASS" ] && ARGS+=("-p$DBPASS")
sqlq() { "$MYSQL" "${ARGS[@]}" -N -B "$DB" -e "$1" 2>/dev/null; }
OK=0; KO=0
ok() { echo "  OK  $1"; OK=$((OK+1)); }
ko() { echo "  KO  $1"; KO=$((KO+1)); }
verif() { if [ "$2" = "$3" ]; then ok "$1 ($3)"; else ko "$1 : attendu $2, obtenu $3"; fi; }

printf '%%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%%%EOF\n' > /tmp/test1.pdf
printf '%%PDF-1.4\n%% deuxieme\ntrailer<<>>\n%%%%EOF\n' > /tmp/test2.pdf
printf '%%PDF-1.4\n%% troisieme\ntrailer<<>>\n%%%%EOF\n' > /tmp/test3.pdf
echo "bonjour" > /tmp/faux.pdf

J=""
jeton() { curl -s -b "$J" -c "$J" "$BASE/$1" | grep -o 'name="csrf_test_name" value="[^"]*"' | head -1 | sed 's/.*value="//; s/"$//'; }
connexion() {
  J="/tmp/jar_$1.txt"; rm -f "$J"
  local t r; t=$(jeton connexion)
  r=$(curl -s -o /dev/null -w "%{http_code} %{redirect_url}" -b "$J" -c "$J" -d "csrf_test_name=$t" -d "email=$1%40demo.mg" -d "mot_de_passe=Demo%402026" "$BASE/connexion")
  case "$r" in 30[23]*connexion*) ko "connexion $1 refusée";; 30[23]*) ok "connexion $1";; *) ko "connexion $1 : réponse « $r »";; esac
}
contexte() { local t; t=$(jeton ""); curl -s -o /dev/null -b "$J" -c "$J" -d "csrf_test_name=$t" -d "societe=$1" -d "periode=$2" -d "retour=/" "$BASE/contexte"; }
page() {
  local code; code=$(curl -s -o /tmp/page.html -w "%{http_code}" -b "$J" -c "$J" "$BASE/$1")
  if [ "$code" != "$2" ]; then ko "GET /$1 : attendu $2, obtenu $code"; return; fi
  if grep -q -E "Whoops|Fatal error|Parse error|Uncaught|ErrorException|<b>Warning</b>|<b>Notice</b>" /tmp/page.html; then ko "GET /$1 : erreur PHP dans la page"; else ok "GET /$1 ($code)"; fi
}
nb_docs() { sqlq "SELECT COUNT(*) FROM documents WHERE societe_id=1"; }
nb_ctrl() { sqlq "SELECT COUNT(*) FROM controles_fiscaux_historique WHERE societe_id=1"; }
poster_bordereau() { # montant date nif fichier
  local t; t=$(jeton bordereaux)
  curl -s -o /dev/null -w "%{http_code}" -b "$J" -c "$J" -F "csrf_test_name=$t" -F "type_document=bordereau" -F "tiers_id=" -F "montant_ttc=$1" -F "date_document=$2" -F "nif_emetteur=$3" -F "fichier=@$4;type=application/pdf" "$BASE/bordereaux"
}
refus_bordereau() { local lib="$1" avant; shift; avant=$(nb_docs); poster_bordereau "$@" >/dev/null; verif "$lib : rien enregistré" "$avant" "$(nb_docs)"; }
poster_controle() { # date impot regle motif montant
  local t; t=$(jeton controles)
  curl -s -o /dev/null -w "%{http_code}" -b "$J" -c "$J" -d "csrf_test_name=$t" -d "date_controle=$1" -d "type_impot=$2" -d "regle_code=$3" --data-urlencode "motif=$4" -d "redressement_montant=$5" "$BASE/controles"
}
refus_controle() { local lib="$1" avant; shift; avant=$(nb_ctrl); poster_controle "$@" >/dev/null; verif "$lib : rien enregistré" "$avant" "$(nb_ctrl)"; }

echo "== Serveur"
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/connexion")
[ "$code" = "200" ] || { echo "KO  $BASE/connexion répond $code : serveur arrêté ou app.baseURL incorrect dans .env"; exit 1; }
ok "page de connexion ($BASE)"

PID=$(sqlq "SELECT id FROM periodes_fiscales WHERE societe_id=1 AND annee=2026 AND mois=10")
AID=$(sqlq "SELECT id FROM anomalies WHERE societe_id=1 ORDER BY id LIMIT 1")
[ -n "$PID" ] && [ -n "$AID" ] || { echo "KO  période 10/2026 ou anomalies introuvables : base vide ? (./demo_preparer.sh)"; exit 1; }

echo; echo "== Accès sans connexion"
J=/tmp/jar_anon.txt; rm -f "$J"
verif "page protégée redirige" 302 "$(curl -s -o /dev/null -w '%{http_code}' -b $J -c $J "$BASE/bordereaux")"
verif "API protégée en JSON 401" 401 "$(curl -s -o /dev/null -w '%{http_code}' -b $J -c $J "$BASE/api/regles")"

for role in comptable fiscal admin; do
  echo; echo "== Pages ($role)"
  connexion $role; contexte 1 $PID; contexte 1 $PID
  for p in "" anomalies "anomalies/$AID" declarations bordereaux controles; do page "$p" 200; done
  if [ "$role" = comptable ]; then page regles 302; page audit 302; else page regles 200; page audit 200; fi
  code=$(curl -s -o /tmp/api.json -w "%{http_code}" -b "$J" -c "$J" "$BASE/api/dashboard/1/$PID")
  verif "API dashboard" 200 "$code"
  if grep -Eq '"valeur": ?"?85' /tmp/api.json; then ok "score 85 dans l'API"; else ko "score différent de 85 (lu : $(grep -o '"valeur": \?"\?[0-9.]*' /tmp/api.json | head -1))"; fi
done

echo; echo "== Saisie des bordereaux (comptable)"
connexion comptable; contexte 1 $PID; contexte 1 $PID
n0=$(nb_docs)
verif "envoi valide : redirection" 303 "$(poster_bordereau '650 000' 2026-10-12 5000000022 /tmp/test1.pdf)"
verif "envoi valide : un document de plus" $((n0+1)) "$(nb_docs)"
DOC=$(sqlq "SELECT MAX(id) FROM documents WHERE societe_id=1")
verif "3 champs enregistrés" 3 "$(sqlq "SELECT COUNT(*) FROM donnees_extraites WHERE document_id=$DOC")"
verif "montant_ttc enregistré" 650000.00 "$(sqlq "SELECT valeur FROM donnees_extraites WHERE document_id=$DOC AND champ='montant_ttc'")"
verif "statut d'extraction" extrait "$(sqlq "SELECT statut_extraction FROM documents WHERE id=$DOC")"
CH=$(sqlq "SELECT chemin FROM documents WHERE id=$DOC")
if [ -f "$CH" ]; then ok "PDF présent sur le disque ($CH)"; else ko "PDF absent du disque : $CH"; fi
if curl -s -D - -o /dev/null -b "$J" -c "$J" "$BASE/bordereaux/$DOC/fichier" | grep -qi "content-type: application/pdf"; then ok "PDF servi par l'application"; else ko "PDF non servi"; fi
refus_bordereau "même PDF (doublon)" 650000 2026-10-12 5000000022 /tmp/test1.pdf
refus_bordereau "faux PDF" 650000 2026-10-12 5000000022 /tmp/faux.pdf
refus_bordereau "montant abc" abc 2026-10-12 5000000022 /tmp/test2.pdf
refus_bordereau "date future" 650000 2999-01-01 5000000022 /tmp/test2.pdf
refus_bordereau "NIF trop court" 650000 2026-10-12 12 /tmp/test2.pdf
n0=$(nb_docs); poster_bordereau 650000 2026-08-10 5000000022 /tmp/test2.pdf >/dev/null
verif "date hors période : acceptée avec avertissement" $((n0+1)) "$(nb_docs)"

echo; echo "== Saisie des bordereaux (responsable fiscal : refusée)"
connexion fiscal; contexte 1 $PID; contexte 1 $PID
refus_bordereau "rôle responsable fiscal" 650000 2026-10-12 5000000022 /tmp/test3.pdf

echo; echo "== Historique des contrôles (responsable fiscal)"
n0=$(nb_ctrl)
verif "contrôle valide : redirection" 303 "$(poster_controle 2025-09-10 IRSA R4 'Ecart de bareme sur le minimum de perception' 1200000)"
verif "contrôle valide : une ligne de plus" $((n0+1)) "$(nb_ctrl)"
refus_controle "motif trop court" 2025-09-10 IRSA R4 ok 1200000
refus_controle "date future" 2999-01-01 IRSA R4 'Motif suffisamment long' 1200000
refus_controle "montant négatif" 2025-09-10 IRSA R4 'Motif suffisamment long' -100

echo; echo "== Historique des contrôles (comptable : refusée)"
connexion comptable; contexte 1 $PID; contexte 1 $PID
refus_controle "rôle comptable" 2025-09-10 TVA R2 'Motif suffisamment long' 500000

echo; echo "== Résultat : $OK OK, $KO KO"
echo "Journal d'audit :"; "${PHP:-/opt/lampp/bin/php}" spark audit:verifier 2>&1 | tail -n 4
echo "Pense à relancer ./demo_preparer.sh (le script a ajouté des données de test)."
