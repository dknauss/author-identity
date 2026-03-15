# Code Review — Hardening and Coverage Plan

**Date:** March 2026
**Source:** Full code review of `byline-feed/` source and test files.
**Purpose:** Actionable post-Gate-A hardening backlog for fixing moderate source issues and closing targeted test coverage gaps. Items are grouped by priority and can be executed independently without reopening the completed Gate A baseline.

---

## Source fixes

### P1 — Moderate issues

#### 1. feed-json.php: add explicit post_type to get_posts()

**File:** `inc/feed-json.php` (~line 222)
**Problem:** `get_posts()` is called without an explicit `post_type` parameter. WordPress defaults to `'post'` only, which will silently exclude custom post types that should appear in the JSON Feed.
**Fix:** Add `'post_type' => get_post_types( array( 'public' => true ) )` or match the post types used by the RSS2 and Atom feed queries.
**Test:** Verify JSON Feed includes a CPT post when a public CPT is registered.

#### 2. fediverse.php: remove duplicate handle normalization

**File:** `inc/fediverse.php` (lines ~59 and ~68)
**Problem:** `normalize_byline_feed_fediverse()` is called twice on the same handle — once when reading meta and again before output. The second call is redundant.
**Fix:** Remove the redundant call. Keep the one closest to output.
**Test:** Existing fediverse tests should continue to pass unchanged.

#### 3. feed-common.php: specify UTF-8 encoding in mb_substr

**File:** `inc/feed-common.php` (line ~26)
**Problem:** `mb_substr( wp_strip_all_tags( $author->description ), 0, 280 )` omits the encoding parameter. WordPress usually defaults to UTF-8, but explicit is safer.
**Fix:** Change to `mb_substr( ..., 0, 280, 'UTF-8' )`.
**Test:** No new test needed; existing feed tests cover description truncation.

#### 4. perspective.php: use require_once instead of require

**File:** `inc/perspective.php` (line ~173)
**Problem:** Asset file inclusion uses `require` instead of `require_once`. Safe today because the function is only called once, but `require_once` prevents double-inclusion if the enqueue hook fires twice.
**Fix:** Change `require` to `require_once`.
**Test:** No new test needed.

### P2 — Defensive improvements

#### 5. feed-rss2.php / feed-atom.php: null-check $wp_query

**Files:** `inc/feed-rss2.php` (~line 46), `inc/feed-atom.php` (equivalent line)
**Problem:** Code accesses `$wp_query->posts` without checking that `$wp_query` itself is set. Always true in feed context, but a null-check costs nothing.
**Fix:** `if ( empty( $wp_query ) || empty( $wp_query->posts ) )`.

#### 6. namespace.php: document filter-validation ordering

**File:** `inc/namespace.php` (~line 101)
**Problem:** The `byline_feed_authors` filter runs, then `validate_author_objects()` re-validates the result. Filter authors who introduce invalid contract entries will have those entries silently dropped. This is correct behavior, but not documented.
**Fix:** Add a docblock note above the filter: "Filtered authors are re-validated against the normalized contract. Invalid entries are dropped and logged."

---

## Test coverage gaps

### P1 — High priority

#### 7. E2E: feed output tests

**Current state:** The `wp-env` + Playwright harness exists and currently covers the perspective panel only. There is still no browser-level coverage for feed output, schema, or fediverse output.
**Plan:** Add at least:
- RSS2 feed loads and contains `<byline:contributors>` block
- Atom feed loads and contains Byline namespace elements
- JSON Feed endpoint returns valid JSON with `_byline` extensions
- HTML head contains `fediverse:creator` meta tag on a singular post
- HTML head contains JSON-LD `Article` + `Person` schema on a singular post

**Files:** `tests/e2e/feed-output.spec.js`, `tests/e2e/fediverse.spec.js`, `tests/e2e/schema.spec.js`

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

- All P1 source fixes (items 1–4) can be done in a single commit.
- P2 source fixes (items 5–6) can be a second commit.
- Test items are independent of each other and can be done in any order.
- E2E tests (item 7) build on the existing `wp-env` + Playwright harness. Source fixes do not.
- None of these items change the plugin's public API or output format.
