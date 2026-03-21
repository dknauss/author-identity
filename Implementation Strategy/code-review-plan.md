# Code Review — Hardening and Coverage Plan

**Date:** March 2026
**Source:** Full code review of `byline-feed/` source and test files.
**Purpose:** Actionable post-Gate-A hardening backlog for fixing moderate source issues and closing targeted test coverage gaps. Items are grouped by priority and can be executed independently without reopening the completed Gate A baseline.

---

## Source fixes

### P1 — Moderate issues (all resolved)

All four P1 source issues have been resolved in the current codebase. Verified 2026-03-20.

#### 1. feed-json.php: ✅ post_type now explicit

**File:** `inc/feed-json.php` (line 226)
**Resolution:** `get_posts()` now includes `'post_type' => get_post_types( array( 'public' => true ) )`.

#### 2. fediverse.php: ✅ double normalization is correct

**File:** `inc/fediverse.php` (lines 59 and 72)
**Resolution:** Re-reviewed — the two `normalize_byline_feed_fediverse()` calls serve different purposes. Line 59 normalizes the adapter's output. Line 72 re-normalizes after the `byline_feed_fediverse_handle` filter, which may return a non-normalized string. Both are necessary. Not a bug.

#### 3. feed-common.php: ✅ UTF-8 encoding specified

**File:** `inc/feed-common.php` (line 26)
**Resolution:** `mb_substr( ..., 0, 280, 'UTF-8' )` already includes the encoding parameter.

#### 4. perspective.php: ✅ uses require_once

**File:** `inc/perspective.php` (line 173)
**Resolution:** Already uses `require_once`. The `enqueue_block_editor_assets` hook fires once per request, so this is safe.

### P2 — Defensive improvements (all resolved)

Both P2 items have been resolved. Verified 2026-03-20.

#### 5. feed-rss2.php / feed-atom.php: ✅ null-check $wp_query added

**Files:** `inc/feed-rss2.php`, `inc/feed-atom.php`
**Resolution:** Guard changed to `if ( empty( $wp_query ) || empty( $wp_query->posts ) )`.

#### 6. namespace.php: ✅ filter-validation ordering documented

**File:** `inc/namespace.php`
**Resolution:** Docblock updated: "Filtered authors are re-validated against the normalized contract. Invalid entries are dropped and logged via log_invalid_author_contract()."

---

## Test coverage gaps

### P1 — High priority

#### 7. E2E: feed output tests ✅ (resolved)

**Resolution:** `tests/e2e/feed-output.spec.js` now covers all five planned assertions in a single spec file:
- RSS2 feed loads and contains `xmlns:byline` namespace and `byline:contributors` block ✅
- Atom feed loads and contains Byline namespace elements ✅
- JSON Feed endpoint returns valid JSON with `_byline` extension at feed level ✅
- HTML head contains JSON-LD `Article` + `Person` schema on a singular post ✅
- HTML head contains `fediverse:creator` meta tag when author has a handle set ✅

All 5 tests verified passing against live Local site (single-instance.local, 2026-03-20). Tests also run against `wp-env` via `npm run test:e2e`.

#### 8. Unit: empty authors array

**Current state:** No test for what happens when no author can be resolved (deleted user, post_author = 0 with no adapter match).
**Plan:** Add tests in `test-feed-rss2.php`, `test-feed-atom.php`, `test-feed-json.php`, and `test-schema.php` confirming graceful output (no crash, valid feed/schema, Byline block omitted or empty).

#### 9. Unit: PPA integration test parity with CAP

**Current state:** CAP integration tests cover WP users, guests, multi-author, and contract shape. PPA integration tests only cover detection, non-empty result, and shape.
**Plan:** Add to `test-integration-ppa.php`:
- Guest author handling
- Multi-author posts
- User meta (byline_feed_*) in PPA context
- Term metadata preferred over user profile

### P2 — Medium priority

#### 10. Future design: ActivityPub-aware fediverse identity source model

**Problem:** The current `fediverse:creator` output only uses an explicit stored fediverse handle. That is correct for the current release, but it creates unnecessary manual setup for sites where the ActivityPub plugin already exposes a trustworthy local user actor identity.

**Recommendation:** Move future fediverse attribution to a source-based model:

- `auto` = derive from local ActivityPub identity when confidently resolvable
- `manual` = use explicit stored handle
- `none` = emit nothing

Do **not** solve this by copying ActivityPub-derived handles into stored user meta and locking that copied value. Keep derived identity derived, and keep manual override separate.

**Design reference:** See [fediverse-identity-design.md](../docs/planning/fediverse-identity-design.md).

**Why backlog, not current release:** This is a UX and identity-model refinement, not a stability fix. It should follow RC stabilization and should ship with explicit PHPUnit + browser coverage.

#### 11. Unit: JSON Feed filter coverage

**Current state:** `byline_feed_json_author_extension` and `byline_feed_json_feed` filters are cleaned up in tearDown but never exercised in tests. Only `byline_feed_json_item` is tested.
**Plan:** Add tests in `test-feed-json.php` for the two untested filters.

#### 12. Unit: special characters in author fields

**Current state:** No test verifies that special characters, HTML entities, or unicode in author names/descriptions survive feed output without causing XML parse errors or JSON encoding failures.
**Plan:** Add a test per feed format (RSS2, Atom, JSON) with an author whose `display_name` contains `<`, `&`, `"`, and unicode characters (e.g., emoji, CJK). Assert the feed remains well-formed.

#### 13. Unit: role mapping completeness

**Current state:** Only `editor` and `author` WordPress roles are tested for mapping to Byline roles. Admin, subscriber, and contributor are untested.
**Plan:** Add a parameterized test (or loop) in `test-adapter-core.php` covering all standard WordPress roles.

#### 14. Unit: REST API meta round-trip

**Current state:** Meta registration for REST is verified (the schema key exists), but no test makes an actual REST request to read/write author meta.
**Plan:** Add a test in `test-author-meta.php` that uses `rest_do_request()` to GET user meta and verify the fediverse field is returned with correct shape.

### P3 — Low priority

#### 15. Feed schema compliance

**Current state:** XML well-formedness is tested via `simplexml_load_string()`, but neither RSS2 nor Atom output is validated against the actual Byline extension schema. JSON Feed is checked with `assertJson()` but not against the JSON Feed 1.1 spec.
**Plan:** Consider adding a schema validation step (XSD for XML feeds, JSON Schema for JSON Feed) as a separate CI check or test helper.

#### 16. Schema plugin coexistence beyond Yoast

**Current state:** Yoast coexistence is covered. Rank Math detection exists in code, but dedicated coexistence tests remain thin, and other schema plugins are not covered.
**Plan:** Add coexistence tests for Rank Math and SEOPress class names. Low urgency — the filter override mechanism works generically.

---

## Execution notes

- All P1 source fixes (items 1–4) are resolved. No commit needed.
- P2 source fixes (items 5–6) are resolved (2026-03-20). `$wp_query` null-checks added; filter-validation ordering documented.
- E2E feed output tests (item 7) are resolved. The remaining test items (8–16) are independent and can be done in any order.
- None of these items change the plugin's public API or output format.
