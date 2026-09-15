#!/usr/bin/env bash
# Construit le bundle distribuable de la skill we-finalise depuis l'arborescence source,
# après l'avoir vérifiée.
#
#   ./package-skill.sh            vérifie puis empaquette
#   ./package-skill.sh --check    vérifie seulement
#
# Pourquoi ce script existe : avant, les sources ne vivaient que dans le zip, et le SKILL.md
# chargé par Claude Code en était une copie à recopier à la main après chaque modification.
# Les deux divergeaient en silence (cf. _tasks/lessons.md, « Skills packagées »). Désormais
# l'arborescence est la source, et le bundle est un produit — il ne peut plus la contredire.

set -euo pipefail
cd "$(dirname "$0")"

SKILL_DIR=".claude/skills/we-finalise"
BUNDLE="$SKILL_DIR/we-finalise.skill"
fail=0

note() { printf '  %s\n' "$1"; }
bad()  { printf '  ✗ %s\n' "$1"; fail=1; }
ok()   { printf '  ✓ %s\n' "$1"; }

echo "→ Vérification de $SKILL_DIR"

# --- Le canal : plus aucune trace de WP-CLI ni des wrappers supprimés ---------------------
# mcp.md documente légitimement que WP-CLI n'est pas installé : on l'exclut de la recherche.
residus=$(grep -rniE 'wp @|wp_alias|eval-file|scp |scripts/[a-z-]+\.sh' \
            "$SKILL_DIR/SKILL.md" "$SKILL_DIR/references" \
            --exclude=mcp.md 2>/dev/null || true)
if [ -n "$residus" ]; then
  bad "mécaniques WP-CLI résiduelles :"
  printf '%s\n' "$residus" | sed 's/^/      /'
else
  ok "aucune mécanique WP-CLI résiduelle"
fi

# --- Les charges PHP -----------------------------------------------------------------------
if command -v php >/dev/null 2>&1; then
  err=""
  for f in "$SKILL_DIR"/scripts/php/*.php; do
    php -l "$f" >/dev/null 2>&1 || err="$err $(basename "$f")"
  done
  if [ -n "$err" ]; then bad "erreurs de syntaxe PHP :$err"; else
    ok "$(ls "$SKILL_DIR"/scripts/php/*.php | wc -l | tr -d ' ') charges PHP, syntaxe valide"
  fi
else
  note "⚠ php absent en local : syntaxe des charges non vérifiée"
fi

# Contexte web : ni exit, ni STDERR — voir references/mcp.md §3.
interdits=$(grep -rnE '\bexit\(|STDERR' "$SKILL_DIR/scripts/php" || true)
if [ -n "$interdits" ]; then
  bad "exit() ou STDERR dans une charge (interdits en contexte web) :"
  printf '%s\n' "$interdits" | sed 's/^/      /'
else
  ok "aucun exit()/STDERR dans les charges"
fi

# La balise d'ouverture PHP ailleurs qu'en première ligne fait rejeter l'appel par le pare-feu
# de l'hébergeur avec un 406 — le diagnostic est déroutant. Voir references/mcp.md §5.
balise="<""?php"
fautives=""
for f in "$SKILL_DIR"/scripts/php/*.php; do
  n=$(tail -n +2 "$f" | grep -cF "$balise" || true)
  [ "$n" = "0" ] || fautives="$fautives $(basename "$f")($n)"
done
if [ -n "$fautives" ]; then
  bad "balise d'ouverture PHP hors première ligne — rejet 406 garanti :$fautives"
else
  ok "aucune balise d'ouverture PHP hors première ligne"
fi

# --- Les scripts Node ----------------------------------------------------------------------
if command -v node >/dev/null 2>&1; then
  err=""
  for f in "$SKILL_DIR"/scripts/*.mjs; do
    node --check "$f" >/dev/null 2>&1 || err="$err $(basename "$f")"
  done
  if [ -n "$err" ]; then bad "erreurs de syntaxe JS :$err"; else ok "scripts Node, syntaxe valide"; fi
else
  note "⚠ node absent en local : syntaxe des .mjs non vérifiée"
fi

# --- Les renvois croisés « fichier.md §N » -------------------------------------------------
manquants=$(
  grep -rhoE '`?[a-z-]+\.md`? §[0-9]+' "$SKILL_DIR/SKILL.md" "$SKILL_DIR/references" 2>/dev/null \
  | tr -d '`' | sort -u \
  | while IFS= read -r ref; do
      file=$(printf '%s' "$ref" | sed 's/ §.*//')
      num=$(printf '%s' "$ref" | sed 's/.*§//')
      target="$SKILL_DIR/references/$file"
      [ -f "$target" ] || { echo "$ref (fichier absent)"; continue; }
      grep -qE "^#+ *§?$num[.)]? |^#+ .*§$num\b" "$target" || echo "$ref"
    done
)
if [ -n "$manquants" ]; then
  bad "renvois §N vers une section introuvable :"
  printf '%s\n' "$manquants" | sed 's/^/      /'
else
  ok "renvois §N cohérents"
fi

# --- Les charges citées existent-elles ? ---------------------------------------------------
citees=$(grep -rhoE 'scripts/php/[a-z-]+\.php' "$SKILL_DIR/SKILL.md" "$SKILL_DIR/references" | sort -u)
absentes=""
for c in $citees; do
  [ -f "$SKILL_DIR/$c" ] || absentes="$absentes $c"
done
if [ -n "$absentes" ]; then bad "charges citées mais absentes :$absentes"; else
  ok "toutes les charges citées existent"
fi

[ "$fail" -eq 0 ] || { echo; echo "✗ Vérification en échec — bundle non reconstruit."; exit 1; }

if [ "${1:-}" = "--check" ]; then
  echo; echo "✓ Vérification passée (--check : rien empaqueté)."
  exit 0
fi

# --- Empaquetage ---------------------------------------------------------------------------
echo
echo "→ Empaquetage de $BUNDLE"
rm -f "$BUNDLE"
( cd .claude/skills && zip -qr we-finalise/we-finalise.skill \
    we-finalise/SKILL.md we-finalise/references we-finalise/scripts \
    -x '*.DS_Store' '*/.*' )

# Le bundle doit contenir exactement l'arborescence source : c'est la garantie anti-divergence.
diff_src=$(
  { cd "$SKILL_DIR" && find SKILL.md references scripts -type f ! -name '.*' | sed 's|^|we-finalise/|' | sort; } \
  | comm -3 - <(unzip -Z1 "$BUNDLE" | grep -v '/$' | sort)
)
if [ -n "$diff_src" ]; then
  bad "le bundle ne correspond pas aux sources :"
  printf '%s\n' "$diff_src" | sed 's/^/      /'
  exit 1
fi

n=$(unzip -Z1 "$BUNDLE" | grep -vc '/$')
printf '  ✓ %s fichiers, %s\n' "$n" "$(du -h "$BUNDLE" | cut -f1 | tr -d ' ')"
echo
echo "✓ $BUNDLE reconstruit et conforme aux sources."
