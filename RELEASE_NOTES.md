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


## Draft: 0.1.0

### Summary

Byline Feed 0.1.0 is the first stable release. It brings structured, multi-author identity to RSS2, Atom, and JSON Feed, JSON-LD, link previews, and the fediverse, from one normalized author source in WordPress.

### Highlights

- Byline metadata in RSS2, Atom, and JSON Feed, now including the Byline spec's JSON Feed model (`_byline.contributors`, `_byline.author`).
- Multi-author support through Co-Authors Plus, PublishPress Authors, and Human Made Authorship, each tested against the real plugin in CI.
- Ordered multi-author JSON-LD, standalone or enriching Yoast SEO and Rank Math; an `organization` mode for posts credited to the site.
- `fediverse:creator` and author meta tags for link previews, with Jetpack's conflicting tag removed.
- Content perspective, and advisory AI-consent signals (`robots`, `TDMRep`, `ai.txt`, feed rights) with an audit log.
- Security hardening: author text can no longer break out of the JSON-LD script element.

Known differences from the pre-1.0 Byline spec, and what is deferred to 0.2.0, are listed in `docs/planning/spec-alignment-0.1.0.md`.

### Links

- Playground demo: https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2Fauthor-identity%2Fmain%2Fplayground%2Foutput-demo.blueprint.json&url=%2F%3Fp%3D1&mode=browser-full-screen&login=no
- Issues and feedback: https://github.com/dknauss/author-identity/issues
- Attached checksum: `byline-feed-0.1.0.zip.sha256`

### AI assistance note

> Portions of this release were developed with AI coding assistance (Claude). Repository ownership, review, and merge accountability remain with the human maintainer.

## Draft: 0.1.0-rc5

### Summary

Byline Feed 0.1.0-rc5 keeps Jetpack from crediting a person on posts attributed to the site.

### Highlights

- Jetpack Social adds `fediverse:creator` from a connected Mastodon account without knowing who a post is attributed to. Byline Feed now removes that tag in organization mode, and whenever it prints its own handles.

### Links

- Attached checksum: `byline-feed-0.1.0-rc5.zip.sha256`

### AI assistance note

> Portions of this release were developed with AI coding assistance (Claude). Repository ownership, review, and merge accountability remain with the human maintainer.

## Draft: 0.1.0-rc4

### Summary

Byline Feed 0.1.0-rc4 adds attribution: who a post is credited to in structured data and link previews.

### Highlights

- Attribution setting per post: its authors, or the site (organization mode). Organization mode names the publisher as the JSON-LD author and suppresses personal author tags and `fediverse:creator`.
- Companion plugins can suggest the mode through `byline_feed_author_mode`; an editor's choice made by hand always wins. First Person (dknauss/first-person-byline) is the reference companion.
- Author meta tags (`author`, `og:author`, `article:author`) for link-preview readers that ignore JSON-LD articles, including Mastodon.
- Stable JSON-LD `@id`s for the article and publisher.
- Feeds do not yet follow organization mode.

### Links

- Attached checksum: `byline-feed-0.1.0-rc4.zip.sha256`

### AI assistance note

> Portions of this release were developed with AI coding assistance (Claude). Repository ownership, review, and merge accountability remain with the human maintainer.

## Draft: 0.1.0-rc3

### Summary

Byline Feed 0.1.0-rc3 is the third release candidate for the Author Identity plugin suite. It completes the current advisory WP-06 surface with feed-level rights summaries, expands real-plugin integration coverage for ActivityPub, and closes the remaining browser-hardening gaps in the editor/profile UI.

### Highlights

- Structured Byline output for RSS2, Atom, and JSON Feed
- Multi-author adapter support for Co-Authors Plus, PublishPress Authors, and HM Authorship
- Fediverse author attribution via `fediverse:creator`
- Ordered multi-author JSON-LD `Article` + `Person` output, including Yoast SEO and Rank Math enrichment modes
- Editorial `Content Perspective` field with block-editor and classic-editor support
- AI-consent signaling with per-author and per-post resolution, feed-level and denied-item rights metadata, `robots` meta, `TDMRep`, `ai.txt`, and admin-side audit logging
- Docker-backed local PHPUnit workflow plus green CI across the supported PHP/WordPress matrix, including real ActivityPub integration coverage and expanded Playwright E2E coverage
- Refreshed public Playground demo routes aligned with the current release candidate

### Links

- Playground demo: https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fdknauss%2Fauthor-identity%2Fcodex%2Fplayground-assets%2Fplayground%2Fpublic%2Foutput-demo.blueprint.json&url=%2F%3Fp%3D1&mode=browser-full-screen&login=no
- Release feedback / stabilization: https://github.com/dknauss/author-identity/issues/17
- Attached checksum: `byline-feed-0.1.0-rc3.zip.sha256`

### AI assistance note

> Portions of this release were developed with AI coding assistance (Claude, Codex). Repository ownership, review, and merge accountability remain with the human maintainer.
