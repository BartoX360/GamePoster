#!/bin/sh
# Zippe la recette, l'importe dans LaraPaper comme « GamePoster (test) » et rapatrie les rendus dans $1
set -e
DIR=$(cd "$(dirname "$0")" && pwd); ROOT=$(cd "$DIR/.." && pwd)
[ -f "$DIR/.env" ] && . "$DIR/.env"
: "${LARAPAPER_SSH:?définir LARAPAPER_SSH (ex. root@mon-serveur), ou le mettre dans dev/.env}"
(cd "$ROOT/recipe/src" && rm -f ../gameposter.zip && zip -q -r ../gameposter.zip .)
scp -q "$ROOT/recipe/gameposter.zip" "$DIR/recipe_test.php" "$DIR/test_payload.json" "$LARAPAPER_SSH":/tmp/
ssh "$LARAPAPER_SSH" 'for f in gameposter.zip recipe_test.php test_payload.json; do docker cp /tmp/$f larapaper-app-1:/tmp/; done && docker exec -u www-data larapaper-app-1 php artisan tinker /tmp/recipe_test.php 2>&1 | grep -E " ok$|Exception|Error|url=|live payload"; rm -rf /tmp/gpr && docker cp larapaper-app-1:/tmp/gpr /tmp/gpr'
mkdir -p "$1"; scp -q "$LARAPAPER_SSH":'/tmp/gpr/*' "$1/"
