# Gap Analysis — Byline Feed Plugin

**Date:** March 2026  
**Scope:** Current audit of `byline-feed/` against the work package specifications (WP-01 through WP-06) and cross-cutting concerns.  
**Method:** File-by-file comparison of the shipped plugin, tests, CI, and governance docs against [implementation-spec.md](../docs/planning/implementation-spec.md) and the individual work package specs.

---

## Work package status summary

| Work Package | Code exists | Tests exist | Status | Quality |
| --- | --- | --- | --- | --- |
| WP-01: Scaffold & Adapters | All planned files | Core, CAP, PPA, contract tests | Implemented | CI-verified |
| WP-02: RSS2 & Atom Output | Both planned files | RSS2 + Atom tests | Implemented | CI-verified |
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
| `byline-feed/docs/output-reference.md` | Adoption / consumer docs | No consumer-facing output reference yet |
| `byline-feed/CONTRIBUTING.md` | Contributor docs | No plugin-local contributor quick-start yet |

---

## Current gaps

These are the meaningful remaining gaps after WP-01/WP-02 completion.

### 1. WP-04, WP-05, and WP-06 are still entirely unimplemented

The adapter layer already normalizes `fediverse` and `ai_consent` fields, but there is no user UI, meta registration, front-end output, or tests for fediverse attribution, JSON-LD, or rights/consent handling. The roadmap is still front-loaded around feeds and perspective only.

### 2. Real-plugin validation is manual, not automated

CAP and PPA now have PHPUnit coverage and were manually verified against a local Studio WordPress site, but CI still does not install real copies of those plugins and run integration jobs against them. That leaves version-drift risk in upstream plugin APIs.

### 3. WP-03 needs stronger editor-specific verification

The perspective feature builds successfully and its feed output is tested, but block-editor behavior is not covered by browser or end-to-end tests. Regressions in panel registration, UI labels, or save behavior would currently be caught only by manual testing.

### 4. Consumer-facing output documentation is still missing

The repository now has contributor/process guidance, but it still lacks the plugin output reference described in the implementation strategy: annotated RSS2, Atom, JSON-LD, and HTML-head examples with filter reference and field mapping.

### 5. Feed layer code duplication and filter naming

`feed-rss2.php` and `feed-atom.php` contain identical `output_person()` functions. Additionally, both layers fire the same `byline_feed_item_xml` filter name, meaning callbacks cannot distinguish RSS2 items from Atom entries. These issues are tracked as refinements R-1 through R-3 in the [implementation spec](implementation-spec.md#pre-wp-04-refinements) and should be resolved before WP-04 adds a third output channel.

### 6. Author meta save/render path has no test coverage

The `save_author_meta_fields()` function in `author-meta.php` handles nonce verification, capability checks, and POST data parsing but has no test. Tracked as refinement R-4 in the [implementation spec](implementation-spec.md#pre-wp-04-refinements).

---

## Structural notes

These are not code defects, but they affect execution strategy.

### 7. Remaining security advisories are development-tooling only

The high-severity npm advisories were resolved. The remaining open Dependabot alerts are moderate `webpack-dev-server` advisories inherited through the current `@wordpress/scripts` toolchain. They affect development tooling, not the shipped plugin runtime, and should be tracked as upstream risk unless the build stack is deliberately changed.

### 8. Release discipline now exists, but needs consistent use

The repository now has `CHANGELOG.md`, `RELEASE_NOTES.md`, issue templates, a PR template, and contributor guidance. The remaining gap is procedural: future releases should consistently update the changelog and apply the release-note convention when AI assistance materially shaped the release.

---

## What's no longer a gap

The following items appeared in earlier audits but are now resolved:

- PHPUnit infrastructure is present (`phpunit.xml.dist`, bootstrap, install script).
- GitHub Actions CI exists and runs PHPCS, PHPUnit, and the Node build.
- CAP, PPA, RSS2, Atom, and author-contract tests exist and pass in CI.
- Atom now has filter parity with RSS2.
- The perspective panel builds successfully.
- Public-repo governance files are present and tracked.

---

## Resolution priority

| Priority | Gaps | Rationale |
| --- | --- | --- |
| **Next product work** | #1 (WP-04/05/06) | The MVP feed layer is in place; remaining roadmap value is in additional output channels |
| **Best risk reduction** | #2 (real-plugin CI validation), #3 (editor verification) | These reduce regression risk in the most integration-heavy areas |
| **Adoption support** | #4 (consumer output reference) | Integration partners need concrete output examples |
| **Process hygiene** | #5 (track dev-tooling advisories), #6 (use changelog and release-note policy consistently) | Keeps maintenance and release quality disciplined without blocking feature work |

---

## Related documents

- [implementation-spec.md](../docs/planning/implementation-spec.md) — Work packages, cross-cutting concerns, delivery schedule
- [wp-01.md](wp-01.md) through [wp-06.md](wp-06.md) — Individual work package specifications
- [docs/quality/TEST_COVERAGE_MATRIX.md](../docs/quality/TEST_COVERAGE_MATRIX.md) — Current test coverage by domain
