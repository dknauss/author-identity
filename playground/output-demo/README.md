# Output Demo Blueprint

This bundle is the primary WordPress Playground target for `byline-feed`.

Purpose:
- demonstrate shipped output channels, not adapter integration
- keep author data deterministic through a small demo mu-plugin
- make RSS2, Atom, JSON Feed, `fediverse:creator`, and JSON-LD easy to inspect in one disposable site

What it installs:
- `byline-feed` from the `main` branch of this repository via `git:directory`
- a small mu-plugin that injects two normalized authors for the default WordPress sample post
- no third-party multi-author plugin dependencies

Local usage:

```bash
npx @wp-playground/cli@latest server --blueprint=playground/output-demo/blueprint.json
```

Expected demo URLs:
- `/?p=1`
- `/feed/`
- `/feed/atom/`
- `/feed/json/`

Right-click "View Frame Source" in Chrome for a clean view to test each feed URL.

Notes:
- This is the source-of-truth bundle for the public Playground demo.
- The public CTA points to the stable `codex/playground-assets` blueprint URL.
- Each refresh republishes that stable blueprint while pinning plugin installation to a fresh immutable source tag for the current commit.
- Local snapshot ZIPs built from this bundle are still useful for offline sharing and local archival demos.
- A separate adapter-demo blueprint for Co-Authors Plus and PublishPress Authors is intentionally deferred.

Public refresh command:

```bash
playground/bin/publish-output-demo.sh
```
