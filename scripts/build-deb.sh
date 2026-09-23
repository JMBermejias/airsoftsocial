#!/usr/bin/env bash
# Genera el paquete .deb de Social Airsoft.
# Uso: ./scripts/build-deb.sh <version>   (ej: 1.2.0)
set -euo pipefail

VERSION="${1:?Uso: build-deb.sh <version> (ej: 1.2.0)}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$STAGE/DEBIAN"

# control con la versión sustituida
sed "s/^Version:.*/Version: $VERSION/" "$ROOT/packaging/deb/DEBIAN/control" > "$STAGE/DEBIAN/control"
cp "$ROOT/packaging/deb/DEBIAN/postinst" "$STAGE/DEBIAN/postinst"

# árbol web de la aplicación (sin git, config local, ni fuentes de empaquetado)
mkdir -p "$STAGE/var/www/socialairsoft"
( cd "$ROOT" && tar \
    --exclude=.git --exclude=config.php --exclude=dist \
    --exclude=android --exclude=packaging --exclude=scripts \
    --exclude=.github --exclude='*.deb' --exclude='*.apk' --exclude='*.aab' \
    -cf - . ) | ( cd "$STAGE/var/www/socialairsoft" && tar -xf - )

find "$STAGE" -type d -exec chmod 755 {} +
find "$STAGE" -type f -exec chmod 644 {} +
chmod 755 "$STAGE/DEBIAN/postinst"

OUT="$ROOT/dist/socialairsoft_${VERSION}_all.deb"
mkdir -p "$ROOT/dist"
if dpkg-deb --root-owner-group --build "$STAGE" "$OUT" 2>/dev/null; then
  :
else
  dpkg-deb --build "$STAGE" "$OUT"
fi

echo "Paquete creado: $OUT"