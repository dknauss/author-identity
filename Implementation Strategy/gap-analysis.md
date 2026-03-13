# Gap Analysis — Byline Feed Plugin

**Date:** March 2026
**Scope:** Audit of all existing plugin code against work package specifications (WP-01 through WP-06) and cross-cutting concerns.
**Method:** File-by-file comparison of `byline-feed/` contents against [implementation-spec.md](../docs/planning/implementation-spec.md) and individual work package specs.

---

## Work package status summary

| Work Package | Code exists | Tests exist | % Complete | Quality |
| --- | --- | --- | --- | --- |
| WP-01: Scaffold & Adapters | All 6 files | 1 of 3 test files | ~75% | Production-ready |
| WP-02: RSS2 & Atom Output | Both files | 1 of 2 test files | ~80% | Production-ready |
| WP-03: Perspective Field | Both files (PHP + TSX) | 1 of 1 test files | ~90% | Production-ready |
| WP-04: fediverse:creator | None | None | ~0% | N/A |
| WP-05: JSON-LD Schema | None | None | 0% | N/A |
| WP-06: Rights & AI Consent | None | None | ~0% | N/A |

---

## Missing files

Files specified in the implementation spec that do not exist:

| File | Work package | Impact |
| --- | --- | --- |
| `tests/phpunit/test-adapter-cap.php` | WP-01 | CAP adapter has zero test coverage |
| `tests/phpunit/test-adapter-ppa.php` | WP-01 | PPA adapter has zero test coverage |
| `tests/phpunit/test-feed-atom.php` | WP-02 | Atom output has zero test coverage |
| `inc/fediverse.php` | WP-04 | No fediverse:creator output |
| `inc/schema.php` | WP-05 | No JSON-LD output |
| `inc/rights.php` | WP-06 | No AI consent/rights output |
| `tests/phpunit/test-fediverse.php` | WP-04 | N/A (no code to test) |
| `tests/phpunit/test-schema.php` | WP-05 | N/A (no code to test) |
| `tests/phpunit/test-rights.php` | WP-06 | N/A (no code to test) |
| `phpunit.xml.dist` | All | Test suite cannot run |
| `.github/workflows/ci.yml` | All | No CI pipeline |

---

## Critical gaps

These would block a real wp.org submission.

### 1. Test suite cannot run

There is no `phpunit.xml.dist`, no test bootstrap, and no WordPress test harness setup script (`bin/install-wp-tests.sh`). The three existing test files extend `WP_UnitTestCase` but there is no infrastructure to execute them. `composer test` would fail immediately.

### 2. Block editor panel has never been built

`src/perspective-panel.tsx` exists but `npm install` and `npm run build` have never been run. There is no `build/` directory and no compiled JavaScript. The perspective sidebar panel would not load on a real WordPress installation.

### 3. Two of three adapter test files are missing

`test-adapter-cap.php` and `test-adapter-ppa.php` don't exist. The Co-Authors Plus and PublishPress Authors adapters — the two adapters that justify the plugin's existence beyond core WordPress — have zero test coverage.

---

## Spec divergences

Code exists but doesn't match what the work package specifications say it should do.

### 4. RSS2 output is missing three Byline elements

The WP-02 spec calls for `<byline:profile>`, `<byline:now>`, and `<byline:uses>` inside `<byline:person>`. The `output_person()` function in `feed-rss2.php` outputs `<byline:name>`, `<byline:context>`, `<byline:url>`, and `<byline:avatar>` but never renders profile links, `/now` URLs, or `/uses` URLs — even though the normalized author objects carry that data in `profiles`, `now_url`, and `uses_url` fields.

### 5. Atom output lacks filter parity with RSS2

RSS2 has four extensibility hooks: `byline_feed_person_xml`, `byline_feed_item_xml`, `byline_feed_after_rss2_contributors`, and `byline_feed_after_rss2_item`. The Atom layer has no parallel hooks. A developer extending the RSS2 output would find the Atom output non-extensible.

### 6. Perspective allowed-values list is duplicated

`inc/perspective.php` defines `get_allowed_values()` and `sanitize_perspective()`. `inc/namespace.php` has its own inline `$allowed` array in `byline_feed_get_perspective()`. If the Byline spec adds a perspective value, it must be updated in two places.

### 7. Role mapping function signature differs from spec

The WP-01 spec defines `byline_feed_map_role( \WP_User $user, \WP_Post $post = null )` with the filter receiving `( $role, $user, $post )`. The implementation uses `get_byline_role_from_user( ?\WP_User $user )` — different name, no `$post` parameter, and the filter receives `( $role, $coauthor_object, null )` with a different second argument type.

### 8. Perspective panel labels don't match spec

The TSX uses simple labels ("Personal", "Reporting", "Analysis") while the WP-03 spec calls for descriptive labels ("Personal / Opinion", "News Reporting", "Analysis / Commentary"). Minor, but it's a divergence.

---

## Structural gaps

Things the plan didn't fully account for, or where implementation assumptions don't hold.

### 9. WP-04, WP-05, and WP-06 are entirely absent

The adapter layer reads `byline_feed_fediverse` and `byline_feed_ai_consent` from user meta, but neither meta key is registered via `register_meta()`, neither has a UI, and neither produces any output. These three work packages have 0% implementation.

### 10. Atom output duplicates RSS2 person-rendering logic

`feed-atom.php` contains its own `output_contributors()` that duplicates `feed-rss2.php`'s `output_person()` logic inline rather than sharing a common rendering function. If the person XML format changes, both files must be updated independently.

### 11. No CI exists

No `.github/workflows/ci.yml`. No automation of any kind. Already addressed in [cross-cutting concern #1](../docs/planning/implementation-spec.md#1-continuous-integration) but confirmed by the audit.

---

## What's NOT a gap

Things that look incomplete but are correctly deferred by the spec:

- **Molongui and HM Authorship adapters missing:** WP-01 spec explicitly defers these to a later work package. The interface and detection logic accommodate them.
- **Conditional output module loading:** The WP-01 spec mentions this but it's premature optimization at 0.1.0-dev. Loading all modules unconditionally is correct for now.
- **No `tsconfig.json`:** `@wordpress/scripts` handles TypeScript compilation internally. A separate tsconfig is optional.
- **No `build/` directory committed:** Build artifacts should not be in version control. The gap is that the build has never been *run*, not that the artifacts are missing from git.

---

## Resolution priority

| Priority | Gaps | Rationale |
| --- | --- | --- |
| **Before any further development** | #1 (test harness), #11 (CI) | Everything else depends on being able to run and verify tests |
| **WP-01 completion** | #3 (missing test files), #7 (role mapping alignment) | Adapter layer is the foundation for all output |
| **WP-02 completion** | #4 (missing Byline elements), #5 (Atom filter parity), #10 (shared rendering) | Feed output is the primary product |
| **WP-03 completion** | #2 (build the TSX), #6 (deduplicate allowed values), #8 (panel labels) | Quick fixes, mostly cleanup |
| **Post-MVP** | #9 (WP-04/05/06) | These are correctly sequenced behind Gate A |

---

## Related documents

- [implementation-spec.md](../docs/planning/implementation-spec.md) — Work packages, cross-cutting concerns, delivery schedule
- [wp-01.md](wp-01.md) through [wp-06.md](wp-06.md) — Individual work package specifications
