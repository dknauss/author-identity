# Release Notes Convention

Use release notes to disclose substantial AI assistance without confusing authorship or accountability.

## Release checklist

For each real release:

1. Create or update the matching version section in [CHANGELOG.md](CHANGELOG.md).
2. Summarize only the externally meaningful changes in the GitHub release notes.
3. Reuse the changelog categories where practical (`Added`, `Changed`, `Fixed`, `Security`, `Docs`).
4. Include the AI-assistance note below when AI materially affected the shipped release.
5. Do not imply AI ownership; keep accountability with the human maintainer.

## Recommended note

When a release includes material AI-assisted work, add a short note like:

> Portions of this release were developed with OpenAI Codex assistance. Repository ownership, review, and merge accountability remain with the human maintainer.

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

- `Assisted-by: Codex` commit trailers
- the AI assistance disclosure in `README.md`
- the contributor/process guidance in `CONTRIBUTING.md`
- the dated version entries in `CHANGELOG.md`


## Draft: 0.1.0-rc1

### Summary

Byline Feed 0.1.0-rc1 is the first release candidate for the Author Identity plugin suite. It ships multi-format Byline feed output for RSS2, Atom, and JSON Feed; adapter support for core WordPress, Co-Authors Plus, PublishPress Authors, and HM Authorship; fediverse author-attribution meta tags; multi-author JSON-LD Article schema; and an initial AI-consent signaling layer.

### Highlights

- Structured Byline output for RSS2, Atom, and JSON Feed
- Multi-author adapter support for Co-Authors Plus, PublishPress Authors, and HM Authorship
- Fediverse author attribution via `fediverse:creator`
- Ordered multi-author JSON-LD `Article` + `Person` output
- Editorial `Content Perspective` field with block-editor and classic-editor support
- Initial AI-consent signaling with per-author and per-post resolution, `robots` meta, `TDMRep`, and `ai.txt`
- Green CI across the supported PHP/WordPress matrix plus Playwright E2E coverage

### AI assistance note

> Portions of this release were developed with OpenAI Codex assistance. Repository ownership, review, and merge accountability remain with the human maintainer.
