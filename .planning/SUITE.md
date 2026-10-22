# Authorship Suite — Planning Hub

This file is the single source of truth for planning across the three
authorship repositories. Each repository keeps its own detailed phase plans,
but the cross-repo picture (what ships next, in what order, and what depends
on what) lives here and nowhere else. When a repository's local planning
docs disagree with this file, this file wins and the local doc gets fixed.

## The repositories

| Repository | What it is | Current release | Default branch | Local planning |
|------------|------------|-----------------|----------------|----------------|
| [dknauss/author-identity](https://github.com/dknauss/author-identity) | Research docs plus the **Byline Feed** plugin: one normalized author contract feeding RSS2/Atom/JSON Feed, JSON-LD, `fediverse:creator`, author meta tags, and AI-consent signals | Byline Feed [`0.1.0`](https://github.com/dknauss/author-identity/releases/tag/v0.1.0) (2026-10-22) | `main` | [`.planning/`](./) (this folder) |
| [dknauss/authorship](https://github.com/dknauss/authorship) | Fork of Human Made's **Authorship** plugin: multi-author and guest-author attribution as the data source | `0.3.0-alpha` | `develop` | [`authorship/.planning/`](https://github.com/dknauss/authorship/tree/develop/.planning) |
| [dknauss/byline](https://github.com/dknauss/byline) | Mirror of the **Byline** feed specification ([bylinespec.org](https://bylinespec.org)), upstream at `tgodier/byline` | Spec 1.0 (draft) | `main` | none; spec questions are tracked here |

## How the pieces fit

```
Authorship (data: who is attributed)          Byline spec (vocabulary)
        │  get_authors(), roles                      │  byline:* elements
        ▼                                            ▼
   Byline Feed adapters ──► normalized author contract ──► output channels
   (core, CAP, PPA, Authorship)                         (feeds, JSON-LD, meta, fediverse, rights)
```

- Byline Feed treats every multi-author plugin, Authorship included, as an
  optional adapter. Authorship never depends on Byline Feed.
- Byline Feed's feed output implements the Byline spec. Where the plugin
  diverges from the spec, the decision is recorded in
  [`docs/planning/spec-alignment-0.1.0.md`](../docs/planning/spec-alignment-0.1.0.md).
- Authorship changes that alter `get_authors()` ordering, roles, or guest
  author shape must be checked against Byline Feed's
  `test-integration-authorship.php`, which runs in Byline Feed CI against
  Authorship `develop`.

## Release order

1. **Byline Feed 0.1.0 (stable)** — released 2026-10-22. Roadmap: [ROADMAP.md](ROADMAP.md).
   Tracking issue [#69](https://github.com/dknauss/author-identity/issues/69)
   (verification [#70](https://github.com/dknauss/author-identity/issues/70),
   spec alignment [#71](https://github.com/dknauss/author-identity/issues/71),
   release [#72](https://github.com/dknauss/author-identity/issues/72)).
2. **Authorship 0.3.x / 0.4.0** — active. Phase 05 (interoperability and
   primary-author compatibility) is planned in
   `authorship/.planning/phases/05-interoperability-and-compatibility-hardening-authorship/`.
3. **Byline Feed 0.2.0** — after Authorship: Molongui adapter, feed handling
   of `organization` author mode, and spec changes deferred from 0.1.0.

## Tracking conventions

- File an issue in the repository whose code or docs will change. Issues for
  the active release carry the label `byline-feed-0.1.0` (or the matching
  `<plugin>-<version>` label for later releases).
- Cross-repo work gets one issue per repository, each linking the others.
- Each repository's local `STATE.md` records only that repository's position;
  the release order above is not repeated there.

## Status log

- 2026-10-22: Byline Feed 0.1.0 released
  ([v0.1.0](https://github.com/dknauss/author-identity/releases/tag/v0.1.0),
  zip plus SHA-256). Issues #69–#72 closed. Still to do: open the Playground
  demo in a browser and confirm it loads the v0.1.0 zip.
- 2026-10-21: Byline Feed 0.1.0 prepared in author-identity#73 (verification,
  spec decisions, release docs); merging it publishes the release.
- 2026-10-21: Planning hub created. Byline Feed 0.1.0 milestone opened
  (verification hardening, spec alignment decisions, release reconciliation,
  release cut). rc4 and rc5 shipped 2026-10-14 and are folded into the 0.1.0
  scope.
