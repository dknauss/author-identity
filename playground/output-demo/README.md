# Output Demo Blueprint

This bundle is the primary WordPress Playground target for `byline-feed`.

Purpose:
- demonstrate shipped output channels, not adapter integration
- keep author data deterministic through a small demo mu-plugin
- make feeds, singular head output, denied-rights signaling, and `ai.txt` easy to inspect in one disposable site
- keep the admin-only AI consent audit trail out of public output while documenting where to inspect it locally

What it installs:
- `byline-feed` from the `main` branch of this repository via `git:directory`
- a small mu-plugin that injects deterministic authors and rights fixtures
- no third-party multi-author plugin dependencies

Local usage:

```bash
npx @wp-playground/cli@latest server --blueprint=playground/output-demo/blueprint.json
```

## Demo routes

Allowed multi-author singular page:
- `/?p=1`
  - inspect HTML source for:
    - `fediverse:creator`
    - JSON-LD `Article` + ordered `Person`

Feed routes:
- `/feed/`
- `/feed/atom/`
- `/feed/json/`
  - inspect for multi-author Byline output driven by the same deterministic author fixture set

Denied rights demos:
- `/?p=101`
  - denied by author consent
  - Jane allows, Sam denies
- `/?p=102`
  - denied by post override

Rights policy route:
- `/ai.txt`

## How to inspect each output

Feeds:
- open `/feed/`, `/feed/atom/`, or `/feed/json/`
- use "View Frame Source" in Chrome for a clean feed view inside Playground

Singular head output:
- open `/?p=1`
- use "View Frame Source"
- search for:
  - `fediverse:creator`
  - `application/ld+json`

Denied rights output:
- open `/?p=101` or `/?p=102`
- use "View Frame Source" to confirm:
  - `<meta name="robots" content="noai, noimageai" />`
- use browser DevTools Network on the document request to confirm the response header:
  - `TDMRep`

`ai.txt`:
- open `/ai.txt`
- confirm the policy body is present

Admin-only audit log:
- local Playground runs can inspect `/wp-admin/tools.php?page=byline-feed-ai-consent-audit-log`
- the public demo URLs use `login=no`, so the audit screen is intentionally not exposed there
- changing per-author or per-post AI consent values in wp-admin should create timestamped audit entries

## Public demo URLs

Primary singular page:
- [/?p=1](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2FAuthor-Identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2F%3Fp%3D1&mode=browser-full-screen&login=no)

Feed routes:
- [/feed/](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2FAuthor-Identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2Ffeed%2F&mode=browser-full-screen&login=no)
- [/feed/atom/](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2FAuthor-Identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2Ffeed%2Fatom%2F&mode=browser-full-screen&login=no)
- [/feed/json/](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2FAuthor-Identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2Ffeed%2Fjson%2F&mode=browser-full-screen&login=no)

Denied rights demos:
- [/?p=101](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2FAuthor-Identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2F%3Fp%3D101&mode=browser-full-screen&login=no)
- [/?p=102](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2FAuthor-Identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2F%3Fp%3D102&mode=browser-full-screen&login=no)
- [/ai.txt](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2FAuthor-Identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2Fai.txt&mode=browser-full-screen&login=no)

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
