# Gap Analysis — Byline Feed Plugin

**Date:** March 2026  
**Scope:** Current audit of `byline-feed/` against the work package specifications (WP-01 through WP-06) and cross-cutting concerns.  
**Method:** File-by-file comparison of the shipped plugin, tests, CI, and governance docs against [implementation-spec.md](../docs/planning/implementation-spec.md) and the individual work package specs.

---

## Work package status summary

| Work Package | Code exists | Tests exist | Status | Quality |
| --- | --- | --- | --- | --- |
| WP-01: Scaffold & Adapters | All planned files | Core, CAP, PPA, contract tests | Implemented | CI-verified |
| WP-02: RSS2, Atom & JSON Feed Output | All three output files | RSS2 + Atom + JSON Feed tests | Implemented | Automated coverage now exists for all three feed formats |
| WP-03: Perspective Field | PHP + TSX present | PHPUnit coverage for feed output | Implemented | Built locally, needs ongoing editor QA |
| WP-04: fediverse:creator | None | None | Not started | N/A |
| WP-05: JSON-LD Schema | None | None | Not started | N/A |
| WP-06: Rights & AI Consent | None | None | Not started | N/A |

---

## Remaining missing files

Files still planned by the implementation strategy that do not yet exist:

| File | Work package | Impact |
| --- | --- | --- |
| `inc/fediverse.php` | WP-04 | No `fediverse:creator` output yet |
| `inc/schema.php` | WP-05 | No JSON-LD article/person graph output yet |
| `inc/rights.php` | WP-06 | No rights / TDM / consent output yet |
| `tests/phpunit/test-fediverse.php` | WP-04 | No automated coverage for fediverse output |
| `tests/phpunit/test-schema.php` | WP-05 | No automated coverage for JSON-LD output |
| `tests/phpunit/test-rights.php` | WP-06 | No automated coverage for rights output |

---

## Current gaps

These are the meaningful remaining gaps after WP-01/WP-02 completion.

### 1. WP-04, WP-05, and WP-06 are still entirely unimplemented

The adapter layer already normalizes `fediverse` and `ai_consent` fields, but there is no user UI, meta registration, front-end output, or tests for fediverse attribution, JSON-LD, or rights/consent handling. The roadmap is still front-loaded around feeds and perspective only.

### 2. WP-03 has manual editor verification, but no automated editor coverage

The perspective feature has now been manually verified on the local Studio site: the editor asset enqueues, the saved value persists, and the saved perspective reaches feed output. However, block-editor behavior is still not covered by browser or end-to-end tests. Regressions in panel registration, UI labels, or save behavior would still be caught manually rather than by CI.

### 3. Gate A status

Gate A is complete.

The MVP feed layer (RSS2 + Atom + JSON Feed), adapter layer, perspective field, contract validation, CI, and manual editor verification are all in place. The latest `main` GitHub Actions run is green, and live local verification confirmed Byline output on RSS2 and Atom feeds.

Gate A is the MVP quality gate for real-world testing and wp.org readiness. It is not the same thing as stable 1.0 spec conformance.

### 4. Backlog: Authorship adapter support

Live verification on `single-site-local.local` confirmed that `byline-feed` correctly emits multi-author Byline output when PublishPress Authors is active. Separate verification on an `authorship`-based site showed that unsupported multi-author plugins can still produce a mismatch between core feed author strings and Byline output. If Human Made Authorship support matters to the target adoption landscape, it should be tracked as a dedicated adapter tranche rather than folded into the current Gate A baseline.

---

## Structural notes

These are not code defects, but they affect execution strategy.

### 6. Remaining security advisories are development-tooling only

The high-severity npm advisories were resolved. The remaining open Dependabot alerts are moderate `webpack-dev-server` advisories inherited through the current `@wordpress/scripts` toolchain. They affect development tooling, not the shipped plugin runtime, and should be tracked as upstream risk unless the build stack is deliberately changed.

### 7. Release discipline now exists, but needs consistent use

The repository now has `CHANGELOG.md`, `RELEASE_NOTES.md`, issue templates, a PR template, and contributor guidance. The remaining gap is procedural: future releases should consistently update the changelog and apply the release-note convention when AI assistance materially shaped the release.

---

## What's no longer a gap

The following items appeared in earlier audits but are now resolved:

- PHPUnit infrastructure is present (`phpunit.xml.dist`, bootstrap, install script).
- GitHub Actions CI exists and runs PHPCS, PHPUnit, and the Node build.
- CAP, PPA, RSS2, Atom, and author-contract tests exist and pass in CI.
- CAP and PPA integration CI jobs download real plugins from wordpress.org and test against live APIs.
- JSON Feed now has automated coverage for document shape, author deduplication, per-item roles, perspective output, omission behavior, and feed metadata.
- The perspective UI has been manually verified on the local Studio site, which surfaced and corrected an editor asset enqueue bug.
- Atom now has filter parity with RSS2 (renamed to `byline_feed_atom_entry_xml`).
- Feed layer code duplication resolved — shared `output_person()` in `feed-common.php` (R-1).
- Atom filter naming resolved — format-specific filter names (R-2).
- Atom role test added (R-3).
- Author meta save/render test coverage added (R-4).
- The perspective panel builds successfully.
- Public-repo governance files are present and tracked.

---

## Resolution priority

| Priority | Gaps | Rationale |
| --- | --- | --- |
| **Current state** | #3 (Gate A complete) | MVP quality gate is satisfied; keep CI green and maintain release discipline |
| **Backlog / adapter expansion** | #4 (Authorship adapter support) | Optional next adapter tranche if HM Authorship matters to the target plugin landscape |
| **Pre-1.0 spec alignment** | Multi-author-per-item divergence, JSON Feed structure divergence, terminology drift (`organization` / `publication` / `publisher`) | Resolve the known Byline-spec structural and terminology issues with the spec author before calling the plugin a stable 1.0 implementation |
| **Next product work** | #1 (WP-04/05/06) | After Gate A, the main remaining roadmap value is in additional output channels |
| **Process hygiene** | #6, #7 (track dev-tooling advisories, use changelog consistently) | Keeps maintenance and release quality disciplined without blocking feature work |

---

## Related documents

- [implementation-spec.md](../docs/planning/implementation-spec.md) — Work packages, cross-cutting concerns, delivery schedule
- [wp-01.md](wp-01.md) through [wp-06.md](wp-06.md) — Individual work package specifications
- [docs/quality/TEST_COVERAGE_MATRIX.md](../docs/quality/TEST_COVERAGE_MATRIX.md) — Current test coverage by domain
