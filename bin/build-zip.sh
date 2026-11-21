#!/usr/bin/env bash
# Build a WordPress-installable zip. The archive root is the kirki-mcp folder.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="$(grep -m1 '^ \* Version:' "$ROOT/kirki-mcp.php" | awk '{print $3}')"
STAGE="$(mktemp -d)"
DEST="$ROOT/dist"
FOLDER="kirki-mcp"
ARCHIVE="kirki-mcp-$VERSION"

mkdir -p "$DEST" "$STAGE/$FOLDER"

cp "$ROOT/kirki-mcp.php" "$ROOT/readme.txt" "$ROOT/README.md" "$ROOT/CHANGELOG.md" "$ROOT/LICENSE" "$STAGE/$FOLDER/"
cp -R "$ROOT/includes" "$STAGE/$FOLDER/includes"

(
	cd "$STAGE"
	rm -f "$DEST/$ARCHIVE.zip"
	zip -qr "$DEST/$ARCHIVE.zip" "$FOLDER"
)

rm -rf "$STAGE"
echo "$DEST/$ARCHIVE.zip"
