#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
ASSET_BRANCH="codex/playground-assets"
SNAPSHOT_SCRIPT="$ROOT_DIR/playground/bin/build-output-demo-snapshot.sh"
SNAPSHOT_ZIP="$ROOT_DIR/playground/dist/byline-feed-output-demo.zip"
DEMO_PLUGIN="$ROOT_DIR/playground/output-demo/demo-mu-plugin.php"
REPO_SLUG="dknauss/Author-Identity"
SOURCE_SHA="$(git -C "$ROOT_DIR" rev-parse HEAD)"
SOURCE_SHORT_SHA="$(git -C "$ROOT_DIR" rev-parse --short=12 HEAD)"
SOURCE_TAG="playground-output-demo-source-${SOURCE_SHORT_SHA}"
PUBLIC_BLUEPRINT_URL="https://raw.githubusercontent.com/${REPO_SLUG}/${ASSET_BRANCH}/playground/public/output-demo.blueprint.json"
PLAYGROUND_URL="https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2FAuthor-Identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2F%3Fp%3D1&mode=browser-full-screen&login=no"
PLAYGROUND_FEED_URL="https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2FAuthor-Identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2Ffeed%2F&mode=browser-full-screen&login=no"
PLAYGROUND_AUTHOR_DENY_URL="https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2FAuthor-Identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2F%3Fp%3D101&mode=browser-full-screen&login=no"
PLAYGROUND_POST_DENY_URL="https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2FAuthor-Identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2F%3Fp%3D102&mode=browser-full-screen&login=no"
PLAYGROUND_AI_TXT_URL="https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2FAuthor-Identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2Fai.txt&mode=browser-full-screen&login=no"
WORKTREE_DIR="$(mktemp -d /tmp/author-identity-playground-assets.XXXXXX)"

cleanup() {
	if [ -d "$WORKTREE_DIR/.git" ] || [ -f "$WORKTREE_DIR/.git" ]; then
		git -C "$ROOT_DIR" worktree remove --force "$WORKTREE_DIR" >/dev/null 2>&1 || true
	fi
	rm -rf "$WORKTREE_DIR"
}
trap cleanup EXIT

if [ ! -f "$DEMO_PLUGIN" ]; then
	echo "Missing demo mu-plugin: $DEMO_PLUGIN" >&2
	exit 1
fi

"$SNAPSHOT_SCRIPT"

if ! git -C "$ROOT_DIR" rev-parse -q --verify "refs/tags/${SOURCE_TAG}" >/dev/null; then
	git -C "$ROOT_DIR" tag "$SOURCE_TAG" "$SOURCE_SHA"
fi

if git -C "$ROOT_DIR" ls-remote --exit-code --heads origin "$ASSET_BRANCH" >/dev/null 2>&1; then
	git -C "$ROOT_DIR" fetch origin "$ASSET_BRANCH"
	git -C "$ROOT_DIR" worktree add --detach "$WORKTREE_DIR" FETCH_HEAD
else
	git -C "$ROOT_DIR" worktree add --detach "$WORKTREE_DIR" HEAD
fi

mkdir -p "$WORKTREE_DIR/playground/dist" "$WORKTREE_DIR/playground/public"
cp "$SNAPSHOT_ZIP" "$WORKTREE_DIR/playground/dist/byline-feed-output-demo.zip"
shasum -a 256 "$WORKTREE_DIR/playground/dist/byline-feed-output-demo.zip" > "$WORKTREE_DIR/playground/dist/byline-feed-output-demo.zip.sha256"

python3 - <<'PY' "$DEMO_PLUGIN" "$WORKTREE_DIR/playground/public/output-demo.blueprint.json" "$SOURCE_TAG"
import json
import sys
from pathlib import Path

plugin_path = Path(sys.argv[1])
out_path = Path(sys.argv[2])
source_tag = sys.argv[3]

php = plugin_path.read_text()

obj = {
    "$schema": "https://playground.wordpress.net/blueprint-schema.json",
    "meta": {
        "title": "Byline Feed Output Demo",
        "description": "Installs Byline Feed from a pinned source tag and injects deterministic multi-author output on the default WordPress sample post and feeds.",
        "author": "Dan Knauss",
    },
    "landingPage": "/?p=1",
    "preferredVersions": {
        "wp": "6.7",
        "php": "8.3",
    },
    "features": {
        "networking": True,
    },
    "steps": [
        {
            "step": "installPlugin",
            "pluginData": {
                "resource": "git:directory",
                "url": "https://github.com/dknauss/Author-Identity",
                "ref": source_tag,
                "refType": "tag",
                "path": "byline-feed",
            },
            "options": {
                "activate": True,
                "targetFolderName": "byline-feed",
            },
        },
        {
            "step": "mkdir",
            "path": "/wordpress/wp-content/mu-plugins",
        },
        {
            "step": "writeFile",
            "path": "/wordpress/wp-content/mu-plugins/byline-feed-playground-demo.php",
            "data": php,
        },
    ],
}

out_path.write_text(json.dumps(obj, indent=2) + "\n")
PY

git -C "$WORKTREE_DIR" add -f playground/dist/byline-feed-output-demo.zip playground/dist/byline-feed-output-demo.zip.sha256 playground/public/output-demo.blueprint.json

if ! git -C "$WORKTREE_DIR" diff --cached --quiet; then
	git -C "$WORKTREE_DIR" commit -m "chore: refresh playground output demo assets for ${SOURCE_SHORT_SHA}" -m "Assisted-by: Codex"
fi

git -C "$WORKTREE_DIR" push origin "HEAD:refs/heads/${ASSET_BRANCH}"
git -C "$ROOT_DIR" push origin "refs/tags/${SOURCE_TAG}"

cat <<EOF
Published blueprint:
  $PUBLIC_BLUEPRINT_URL

Playground launch URL:
  $PLAYGROUND_URL

Additional demo URLs:
  feed: $PLAYGROUND_FEED_URL
  per-author deny: $PLAYGROUND_AUTHOR_DENY_URL
  per-post deny: $PLAYGROUND_POST_DENY_URL
  ai.txt: $PLAYGROUND_AI_TXT_URL

Pinned plugin source:
  tag $SOURCE_TAG -> $SOURCE_SHA
EOF
