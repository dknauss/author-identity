# Release Notes Convention

Use release notes to disclose substantial AI assistance without confusing authorship or accountability.

## Release checklist

For each real release:

1. Build the distributable plugin zip with `byline-feed/bin/build-plugin-zip.sh`.
2. Create or update the matching version section in [CHANGELOG.md](CHANGELOG.md).
3. Summarize only the externally meaningful changes in the GitHub release notes.
4. Reuse the changelog categories where practical (`Added`, `Changed`, `Fixed`, `Security`, `Docs`).
5. Link directly to the active Playground demo and release-feedback/stabilization issue when they exist.
6. Mention the attached checksum asset when distributing a release zip.
7. Include the AI-assistance note below when AI materially affected the shipped release.
8. Do not imply AI ownership; keep accountability with the human maintainer.

## Recommended note

When a release includes material AI-assisted work, add a short note like:

> Portions of this release were developed with AI coding assistance (Claude, Codex). Repository ownership, review, and merge accountability remain with the human maintainer.

## When to include it

Include the note when AI assistance materially affected:

- implementation
- documentation
- test coverage
- CI or tooling changes

## When it can be omitted

You can omit the note when AI assistance was trivial or purely editorial.

## Relationship to commit history

Release notes complement, but do not replace:

- `Co-Authored-By:` commit trailers
- the AI assistance disclosure in `README.md`
- the contributor/process guidance in `CONTRIBUTING.md`
- the dated version entries in `CHANGELOG.md`


## Draft: 0.1.0-rc2

### Summary

Byline Feed 0.1.0-rc2 is the second release candidate for the Author Identity plugin suite. It keeps the same core output surface as rc1 while hardening the release around shipped schema enrichment, rights signaling, integration coverage, Playground validation, and local test reproducibility.

### Highlights

- Structured Byline output for RSS2, Atom, and JSON Feed
- Multi-author adapter support for Co-Authors Plus, PublishPress Authors, and HM Authorship
- Fediverse author attribution via `fediverse:creator`
- Ordered multi-author JSON-LD `Article` + `Person` output, including Yoast SEO and Rank Math enrichment modes
- Editorial `Content Perspective` field with block-editor and classic-editor support
- AI-consent signaling with per-author and per-post resolution, denied-item feed rights metadata, `robots` meta, `TDMRep`, `ai.txt`, and admin-side audit logging
- Docker-backed local PHPUnit workflow plus green CI across the supported PHP/WordPress matrix, adapter integrations, and Playwright E2E coverage
- Refreshed public Playground demo routes aligned with the current release candidate

### Links

- Playground demo: https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2Fauthor-identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2F%3Fp%3D1&mode=browser-full-screen&login=no
- Release feedback / stabilization: https://github.com/dknauss/author-identity/issues/17
- Attached checksum: `byline-feed-0.1.0-rc2.zip.sha256`

### AI assistance note

> Portions of this release were developed with AI coding assistance (Claude, Codex). Repository ownership, review, and merge accountability remain with the human maintainer.
