# Gap Analysis — Byline Feed Plugin

**Date:** March 2026  
**Scope:** Current audit of `byline-feed/` against the work package specifications (WP-01 through WP-06) and cross-cutting concerns.  
**Method:** File-by-file comparison of the shipped plugin, tests, CI, and governance docs against [implementation-strategy/implementation-spec.md](implementation-spec.md) and the individual work package specs.

---

## Work package status summary

| Work Package | Code exists | Tests exist | Status | Quality |
| --- | --- | --- | --- | --- |
| WP-01: Scaffold & Adapters | All planned files | Core, CAP, PPA, HM, contract tests | Implemented | CI-verified |
| WP-02: RSS2, Atom & JSON Feed Output | All three output files | RSS2 + Atom + JSON Feed tests | Implemented | Automated coverage now exists for all three feed formats |
| WP-03: Perspective Field | PHP + TSX present | PHPUnit + Playwright coverage | Implemented | Browser-verified in a self-contained `wp-env` harness |
| WP-04: fediverse:creator | Output module + user meta/UI | Meta-tag, normalization, and profile-field tests | Implemented | Automated coverage exists; ActivityPub integration remains conservative |
| WP-05: JSON-LD Schema | All three modes | 29 schema tests covering all modes | Implemented | Mode A (Yoast enrichment via `wpseo_schema_article`), Mode B (Rank Math via `rank_math/json_ld`), and Mode C (standalone) all ship. Person objects include `bylineRole`, `aiTrainingConsent`, and fediverse `sameAs`. Article includes `bylinePerspective`. Live-verified with Yoast SEO 27.2. |
| WP-06: Rights & AI Consent | All output modules + block editor panel | 17 rights tests + E2E | Mostly implemented | HTML/header/`ai.txt`/denied-item feed rights ship; block editor consent panel ships; audit logging now ships; channel-wide/feed-wide rights metadata remains |

---

## Remaining missing files

Files still planned by the implementation strategy that do not yet exist:

| File | Work package | Impact |
| --- | --- | --- |
| `inc/rights.php` | WP-06 | Present; current slice covers consent resolution plus HTML/header/`ai.txt` output |
| `tests/phpunit/test-rights.php` | WP-06 | Present; current slice covered by automated tests |

---

## Current gaps

These are the meaningful remaining gaps after WP-04 completion.

### 1. WP-04 and WP-05 are fully implemented; WP-06 is mostly implemented

WP-04 is no longer a gap. The plugin now has:

- plugin-owned user meta and profile UI for fediverse handles
- `fediverse:creator` meta tag output on singular content
- a conservative `ap_actor_url` resolution field for linked WordPress users
- PHPUnit coverage for handle normalization, profile UI, and meta-tag rendering

WP-05 is now fully implemented across all three modes:

- **Mode A (Yoast active):** `schema-yoast.php` hooks `wpseo_schema_article` to replace Yoast's single-author `@id` reference with a full multi-author Person array. Person objects include `bylineRole`, `aiTrainingConsent`, fediverse `sameAs`, and profile links. Article gets `bylinePerspective` as `additionalProperty`. Live-verified with Yoast SEO 27.2.
- **Mode B (Rank Math active):** `schema-rankmath.php` hooks `rank_math/json_ld` to find Article nodes and apply equivalent enrichment via the shared Person builder.
- **Mode C (standalone):** JSON-LD `Article` + ordered `Person` schema on singular content with full field coverage including `bylineRole`, `aiTrainingConsent`, `bylinePerspective`, and fediverse `sameAs`.
- Mode dispatch in `register_hooks()` with `byline_feed_schema_mode` filter for override.
- `fediverse_profile_url()` resolves `@user@instance` to canonical HTTPS URL for `sameAs`.
- 29 PHPUnit tests covering mode detection, standalone output, Yoast enrichment (simulated), Rank Math enrichment (simulated), Person additionalProperty, fediverse URL resolution, and filter extensibility.

WP-06 now covers:

- Per-author user meta and user profile UI for AI consent
- Per-post AI consent override with classic editor metabox and block editor `PluginDocumentSettingPanel`
- Consent resolution logic (most-restrictive-wins for multi-author, post override beats author)
- HTML `noai, noimageai` robots meta tag and HTTP `TDMRep` header on denied posts
- `ai.txt` endpoint with configurable policy URL
- Feed-level `<byline:rights consent="deny" policy="..."/>` in RSS2 and Atom items
- JSON Feed `_byline.rights` object with consent and policy fields
- 17 PHPUnit tests covering resolution, output, metabox save/load, feed rights, and editor script
- 2 Playwright E2E tests for user profile and classic editor consent fields

The remaining WP-06 gap is: channel-wide or feed-wide rights metadata beyond the current denied-item feed signaling, plus richer rights UI hardening.

`ap_actor_url` is now part of the official WP-04/WP-05 design boundary, but only as a cross-cutting field for those work packages. It is not a standalone roadmap item. `did:web:` remains vision-level future work and should not be treated as an active post-Gate-A deliverable.

### 2. WP-03 now has automated block-editor coverage; remaining UI hardening is narrower

The perspective feature has now been manually verified on the local Studio site and covered by a committed Playwright test that runs against a self-contained `wp-env` environment. Regressions in panel registration, UI labels, save behavior, and post-reload persistence are now caught automatically.

The remaining UI hardening items are now narrower and lower priority:

- add browser coverage for the fediverse profile field
- add browser coverage for the classic editor perspective metabox fallback

### 3. Gate A status

Gate A is complete.

The MVP feed layer (RSS2 + Atom + JSON Feed), adapter layer, perspective field, contract validation, CI, and manual editor verification are all in place. The latest `main` GitHub Actions run is green, and live local verification confirmed Byline output on RSS2 and Atom feeds.

Gate A is the MVP quality gate for real-world testing and wp.org readiness. It is not the same thing as stable 1.0 spec conformance.

### 4. HM Authorship support is now implemented; next roadmap gap is WP-06

Live verification on `single-site-local.local` confirmed that `byline-feed` correctly emits multi-author Byline output when PublishPress Authors is active. Separate verification on an `authorship`-based site showed that unsupported multi-author plugins can still produce a mismatch between core feed author strings and Byline output.

Human Made Authorship is no longer a planned tranche. It now ships as a supported adapter with:

- `Adapter_Authorship` in the plugin
- unit normalization coverage
- real-plugin integration coverage in CI
- a real adapter-detection path between PPA and CAP

What remains after that tranche is:

- the remaining WP-06 work beyond the current HTML/header/`ai.txt` slice
- future adapter expansion such as Molongui
- continued live verification against supported upstream plugins

---

## Structural notes

These are not code defects, but they affect execution strategy.

### 5. Development-tooling security posture is now clean, but still separate from runtime risk

The previously open npm advisories were resolved through targeted dependency updates and overrides. The important policy point remains: JavaScript development tooling should be evaluated separately from shipped plugin runtime behavior, and future npm advisories should not be treated as equivalent to a PHP runtime defect without checking the actual delivery path.

### 6. Release discipline now exists, but needs consistent use

The repository now has `CHANGELOG.md`, `RELEASE_NOTES.md`, issue templates, a PR template, and contributor guidance. The remaining gap is procedural: future releases should consistently update the changelog and apply the release-note convention when AI assistance materially shaped the release.

### 7. Research backlog: semantic-publishing rationale is stronger than immediate scope

The reorganized research set now makes a clearer distinction between current roadmap inputs and exploratory semantic-publishing work. The exploratory documents strengthen the long-term rationale for:

- WP-05 JSON-LD
- persistent identifiers later (`ORCID`, `ROR`, `DOI`)
- an eventual publication/organization model beyond just author bylines

But they do not justify expanding the near-term plugin scope yet. Near-term execution now runs through HM Authorship support first, and only then WP-06 before broader graph or identifier work.

Related scope rule:

- `ap_actor_url` is in-scope as a concrete WP-04/WP-05 design field.
- `did:web:` remains deferred future identity work until the current output roadmap ships and there is a concrete consumer for DID-based identity.

### 8. Testing roadmap should stay specific, not generic

The remaining testing work is no longer "add more tests" in the abstract. The roadmap should keep naming the concrete testing tranches that matter:

- real ActivityPub-plugin integration coverage for `ap_actor_url`
- fediverse profile field browser coverage
- classic editor metabox browser coverage
- optional later spec-conformance and round-trip parsing tests for Byline output

### 9. Playground roadmap should stay two-tiered

The repository now has a stable Playground output-demo bundle. That should remain the primary demo target because it showcases shipped outputs from deterministic normalized-author fixtures rather than from fragile third-party plugin setup.

The later Playground backlog item should be separate:

- adapter-demo blueprint showcasing real Co-Authors Plus and PublishPress Authors behavior

That later target is useful, but it should stay secondary. The public demo path should optimize for stable inspection of RSS2, Atom, JSON Feed, `fediverse:creator`, and JSON-LD output. Adapter realism belongs in CI, local integration verification, and a later specialized Playground bundle.

---

## What's no longer a gap

The following items appeared in earlier audits but are now resolved:

- PHPUnit infrastructure is present (`phpunit.xml.dist`, bootstrap, install script).
- GitHub Actions CI exists and runs PHPCS, PHPUnit, and the Node build.
- CAP, PPA, RSS2, Atom, and author-contract tests exist and pass in CI.
- CAP and PPA integration CI jobs download real plugins from wordpress.org and test against live APIs.
- Live CAP and PPA verification on the same two-author post now has linked-user URL parity for the tested local case; CAP falls back to `user_url` when `website` is empty.
- JSON Feed now has automated coverage for document shape, author deduplication, per-item roles, perspective output, omission behavior, and feed metadata.
- The perspective UI has been manually verified on the local Studio site, which surfaced and corrected an editor asset enqueue bug.
- The block editor perspective panel now has committed Playwright coverage via a self-contained `wp-env` harness.
- A stable Playground output-demo bundle now exists for deterministic inspection of shipped outputs, and a public Playground launch now works through an immutable published blueprint; the CAP/PPA adapter-demo bundle remains later backlog work.
- Atom now has filter parity with RSS2 (renamed to `byline_feed_atom_entry_xml`).
- Feed layer code duplication resolved — shared `output_person()` in `feed-common.php` (R-1).
- Atom filter naming resolved — format-specific filter names (R-2).
- Atom role test added (R-3).
- Author meta save/render test coverage added (R-4).
- The perspective panel builds successfully.
- The AI consent block editor panel builds and enqueues alongside the perspective panel.
- Feed-level rights metadata (`<byline:rights>` in RSS2/Atom, `_byline.rights` in JSON Feed) emits for denied posts and is absent for allowed/unset posts. Live-verified on `single-instance.local`.
- Public-repo governance files are present and tracked.

---

## Resolution priority

| Priority | Gaps | Rationale |
| --- | --- | --- |
| **Current state** | #3 (Gate A complete) | MVP quality gate is satisfied; keep CI green and maintain release discipline |
| **Immediate hardening** | ~~P1 source fixes~~ ✅ resolved; P1 E2E feed-output tests ✅ resolved | All P1 source issues and feed-output E2E coverage verified 2026-03-20 |
| **Current next roadmap work** | ~~WP-05 Modes A/B~~ ✅ implemented | Yoast/Rank Math enrichment and standalone field enrichment all ship; byline data now reaches NLWeb agents on Yoast installs |
| **Post-Gate-A hardening** | #8, #9 (specific testing roadmap and staged Playground demos) | Keep extending verification depth and demo quality without reopening Gate A |
| **WP-06 continuation** | ~~denied-item feed rights~~ ✅ implemented; ~~block editor consent UI~~ ✅ implemented; ~~audit logging~~ ✅ implemented; channel-wide/feed-wide rights metadata remains | Denied-item rights in RSS2/Atom/JSON Feed, block editor consent panel, and admin-side audit logging now ship; remaining work is broader feed-wide rights expression |
| **Later adapter work** | Molongui and any other unsupported multi-author plugins | Lower priority than WP-05/WP-06 and should meet the same real-plugin validation bar HM now has |
| **Pre-1.0 spec alignment** | Multi-author-per-item divergence, JSON Feed structure divergence, terminology drift (`organization` / `publication` / `publisher`) | Resolve the known Byline-spec structural and terminology issues with the spec author before calling the plugin a stable 1.0 implementation |
| **Process hygiene** | #5, #6 (track dev-tooling advisories, use changelog consistently) | Keeps maintenance and release quality disciplined without blocking feature work |

---

## Related documents

- [implementation-strategy/implementation-spec.md](implementation-spec.md) — Work packages, cross-cutting concerns, delivery schedule
- [wp-01.md](wp-01.md) through [wp-06.md](wp-06.md) — Individual work package specifications
- [docs/quality/TEST_COVERAGE_MATRIX.md](../docs/quality/TEST_COVERAGE_MATRIX.md) — Current test coverage by domain
