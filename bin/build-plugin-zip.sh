#!/usr/bin/env bash
# Build an installable WordPress plugin zip.
# The archive contains osm-badge-supplier/ at the top, which is what
# Plugins → Add New → Upload Plugin expects. Development files listed in
# .distignore are left out. The relay companion is not part of the plugin.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
version="$(php -r 'preg_match("/^[ \t]*\*[ \t]*Version:[ \t]*(\S+)/m", file_get_contents($argv[1]), $m) || exit(1); echo $m[1];' "$root/osm-badge-supplier.php")"
out="${1:-"$root/osm-badge-supplier.zip"}"
stage="$(mktemp -d)"
dest="$stage/osm-badge-supplier"
mkdir -p "$dest" "$(dirname "$out")"

excludes=()
while IFS= read -r line || [[ -n "$line" ]]; do
	line="${line%%#*}"
	line="$(printf '%s' "$line" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
	if [[ -z "$line" ]]; then
		continue
	fi
	excludes+=( --exclude="$line" )
done < "$root/.distignore"

tar -C "$root" "${excludes[@]}" -cf - . | tar -C "$dest" -xf -
rm -f "$out"
(
	cd "$stage"
	zip -r -q "$out" osm-badge-supplier
)
rm -rf "$stage"

if ! unzip -l "$out" | grep -q 'osm-badge-supplier/osm-badge-supplier.php'; then
	echo "Zip is missing the plugin bootstrap." >&2
	exit 1
fi
if unzip -l "$out" | grep -Eq 'osm-relay-worker/|tests/|vendor/'; then
	echo "Zip contains development files." >&2
	exit 1
fi

echo "$out"
