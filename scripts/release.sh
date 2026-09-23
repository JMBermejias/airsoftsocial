#!/usr/bin/env bash
# Crea un commit + tag e impulsa todo a GitHub.
# GitHub Actions construye entonces el .deb, el APK y el AAB automáticamente
# y los publica en https://github.com/JMBermejias/socialairsoft/releases
#
# Uso:  ./scripts/release.sh v1.2.0 [mensaje breve]
set -euo pipefail
cd "$(dirname "$0")/.."

TAG="${1:?Uso: release.sh v1.2.0 [mensaje]}"
MSG="${2:-Release $TAG}"

if ! git rev-parse --git-dir >/dev/null 2>&1; then
  echo "Error: no hay repositorio git." >&2; exit 1
fi

git add -A
if ! git diff --cached --quiet; then
  git commit -q -m "$MSG"
  echo "Commit creado: $(git rev-parse --short HEAD)"
else
  echo "Sin cambios que commitear."
fi

git push origin main
git tag "$TAG"
git push origin "$TAG"

echo ""
echo "Release $TAG lanzada. GitHub Actions está creando:"
echo "  - socialairsoft_${TAG#v}_all.deb"
echo "  - socialairsoft-${TAG#v}.apk (firmado)"
echo "  - socialairsoft-${TAG#v}.aab (firmado)"
echo "Disponibles en: https://github.com/JMBermejias/socialairsoft/releases"