# Known Gaps, Security Notes, and Hardening Opportunities

This document supplements the Phase 01 audit of the Human Made Authorship plugin with
additional findings from the source-level architecture review. It also tracks gaps in the
byline-feed plugin, which extends multi-author data into structured feed output, schema
enrichment, and AI agent discovery.

**Last updated:** 2026-03-20
**Cross-references:**
- `implementation-strategy/gap-analysis.md` — work-package-level status and priority
- `implementation-strategy/code-review-plan.md` — source fixes and test coverage backlog
- `docs/research/current/nlweb-yoast-context.md` — NLWeb/Yoast landscape analysis

---

## Security

### Guest author login is not actively blocked

**Severity:** Low (mitigated by design, but defense-in-depth gap)
**Status:** Open — Authorship plugin upstream

Guest authors are `WP_User` rows with the `guest-author` role (zero capabilities). Authorship does not register an `authenticate` filter or `wp_login` action to prevent login. The defense relies on:

- Passwords are random 24-character strings generated at creation time, never returned to any caller.
- Email is set to empty string by default, preventing password reset.
- Even if authenticated, the session has zero capabilities.

**Risk scenario:** An Administrator creates a guest author with an email address. The guest author uses WordPress's password reset flow to obtain credentials. They log in with an empty-capability session. The session itself may have side effects with plugins that check `is_user_logged_in()` rather than specific capabilities.

**Recommendation:** Add an `authenticate` filter that returns `WP_Error` for users whose only role is `guest-author`. This is a one-line defense-in-depth addition:

```php
add_filter( 'authenticate', function( $user ) {
    if ( $user instanceof WP_User && in_array( GUEST_ROLE, $user->roles, true ) && count( $user->roles ) === 1 ) {
        return new WP_Error( 'guest_author_login_blocked', __( 'Guest authors cannot log in.', 'authorship' ) );
    }
    return $user;
}, 100, 1 );
```

### Guest author username normalization

**Severity:** Low (edge case)
**Status:** Open — Authorship plugin upstream

`create_item()` in `class-users-controller.php:194-195` derives usernames from the display name:

```php
$username = sanitize_title( sanitize_user( $request->get_param( 'name' ), true ) );
$username = preg_replace( '/[^a-z0-9]/', '', $username );
```

This can produce empty strings for non-ASCII names (e.g., Japanese, Arabic, Chinese names) or collide for near-duplicate display names.

### Signup validation filter scope

**Severity:** Low (code hygiene)
**Status:** Open — Authorship plugin upstream

`create_item()` adds an anonymous `wpmu_validate_user_signup` filter and never removes it. This is a request-scoped side effect that is inconsistent with the pattern used in `get_items()` where the filter is explicitly removed after use.

---

## Data integrity

### Post-insert author assignment failures are silent

**Status:** Open — Authorship plugin upstream

`InsertPostHandler::action_wp_insert_post()` catches exceptions from `set_authors()` and discards them. This means author attribution can silently fail during post save, migration, or programmatic post creation. The REST API path handles the same exceptions by returning `WP_Error`.

### `post_author` field divergence

**Status:** Open — Authorship plugin upstream
**Relevance to byline-feed:** The byline-feed adapter layer reads from the multi-author
plugin's source of truth (taxonomy term, Co-Authors Plus coauthors, etc.), not from
`post_author`. But theme code or other plugins reading `post_author` directly will still
show stale data on sites using Authorship.

WordPress core's `post_author` field on `wp_posts` is not the source of truth for Authorship — the hidden taxonomy is. However, `post_author` continues to exist and may be set/read by other plugins and themes. Authorship does not currently synchronize `post_author` with the first attributed author.

This can cause divergence where `$post->post_author` says user A but Authorship says users B and C. Theme code that reads `post_author` directly (rather than using `the_author()` or Authorship's template functions) will show stale data.

### Object cache considerations

Taxonomy term lookups and `get_users()` calls are cached by WordPress's object cache. On persistent cache backends (Redis, Memcached), stale cache entries after attribution changes could show incorrect authors. Authorship relies on WordPress's built-in cache invalidation for `wp_set_post_terms()` and `get_users()`, which is generally correct but worth noting for debugging.

---

## Performance

### Author archive queries

Author archives use the `action_pre_get_posts()` taxonomy rewrite, which converts `author` and `author_name` query vars into `tax_query` clauses. This is more performant than a post meta query but involves an additional join compared to the native `post_author` column index.

On sites with Elasticsearch (e.g., WordPress VIP), this is likely irrelevant as the taxonomy query will be handled by ES. On MySQL-only sites with very large post tables, the join performance should be tested.

### Editor component render behavior

**Status:** Open — Authorship plugin upstream

`AuthorsSelect.tsx` performs state initialization and can trigger `apiFetch()` from render-time conditionals.

---

## Feed output

**Status: Structured Byline output now ships in RSS2, Atom, and JSON Feed (WP-02 complete)**

The following items from the original gap analysis are **resolved:**

- RSS2 now outputs `<byline:contributors>` with structured `<byline:person>` entries (id, name, context, url, avatar, profiles, /now, /uses) at the channel level, plus per-item `<byline:author ref="..."/>`, `<byline:role>`, and `<byline:perspective>`.
- Atom outputs equivalent Byline namespace elements at the feed and entry level.
- JSON Feed 1.1 outputs `_byline` extension objects with full per-author metadata and per-item perspective/role.

### Remaining feed gaps

- **No `dc:creator` output.** byline-feed uses its own Byline namespace rather than Dublin Core. Individual co-author attribution uses `<byline:author ref="..."/>` referencing the contributor block, not `<dc:creator>`. Feed consumers expecting `dc:creator` for multi-author content will not find it. This is a design decision (Byline namespace is richer), not a bug, but interoperability with legacy consumers is reduced.
- **No Schema.org / JSON-LD embedded in feed items.** Schema output is on HTML pages only (`wp_head`). Feed items do not carry inline JSON-LD. This is standard practice (JSON-LD in feeds is not widely supported by feed readers) but worth noting for completeness.
- ~~**Feed-level rights metadata not yet shipped.**~~ ✅ Resolved (2026-03-20). RSS2/Atom emit `<byline:rights consent="deny" policy="..."/>` on denied items; JSON Feed emits `_byline.rights` object.

**Context (March 2026):** Microsoft's NLWeb project explicitly identifies RSS and Schema.org
as the semantic foundation of its AI agent query protocol. The same enrichment that byline-feed
delivers to feed subscribers — structured author identity, contributor role, editorial
perspective, rights signals — is directly relevant to what NLWeb agents consume. Feed output
and schema output are different channels for the same underlying data.

See `docs/research/current/nlweb-yoast-context.md` for the full NLWeb/Yoast landscape analysis.

---

## Schema output and AI agent discovery

### WP-05: Yoast/Rank Math enrichment modes — implemented

**Status: All three modes implemented and live-verified (2026-03-20)**

The WP-05 work package specifies three modes, all now shipping:

- **Mode A (Yoast active):** `schema-yoast.php` hooks `wpseo_schema_article` to replace Yoast's single-author `@id` reference with a full multi-author Person array. Person objects include `bylineRole`, `aiTrainingConsent`, fediverse `sameAs` (resolved to canonical URL), and profile links. Article gets `bylinePerspective` as `additionalProperty`. Live-verified with Yoast SEO 27.2 — enriched data appears in Yoast's schema graph with no duplicate standalone output.
- **Mode B (Rank Math active):** `schema-rankmath.php` hooks `rank_math/json_ld` to find Article/BlogPosting/NewsArticle nodes and apply equivalent enrichment via the shared Person builder.
- **Mode C (no SEO plugin):** Standalone `<script type="application/ld+json">` output with full field coverage.

Mode dispatch happens in `register_hooks()` with the `byline_feed_schema_mode` filter available for override. The `fediverse_profile_url()` function resolves `@user@instance` handles to `https://instance/@user` for `sameAs` inclusion.

### Standalone schema output: field coverage — resolved

**Status:** ✅ Resolved (2026-03-20)

`get_person_schema()` now includes all WP-05 specified fields:
- `name`, `url`, `description`, `image`, `sameAs` (from profiles + fediverse canonical URL + `ap_actor_url`)
- `additionalProperty` with `bylineRole` (from `$author->role`)
- `additionalProperty` with `aiTrainingConsent` (from `$author->ai_consent`)

Article-level `bylinePerspective` is injected as `additionalProperty` in all three modes.

### `@id` graph URI generation

**Status:** Open — not yet designed

byline-feed does not generate `@id` URIs for author entities. In Yoast integration mode (Mode A), the spec references existing Yoast `@id` values. In standalone mode, the current output uses inline Person objects without `@id` — valid schema, but entities are not referenceable across the graph. If standalone schema output needs to be consumed as a graph (e.g., by a future non-Yoast schemamap implementation), an `@id` scheme will be needed.

### `worksFor` / affiliation gap

**Status:** Open — future opportunity

The Byline spec includes `byline:affiliation` but byline-feed does not yet wire it to
Schema.org `worksFor` in schema output. Yoast populates this from its Knowledge Graph
settings. In Yoast integration mode, we could pass through Yoast's `worksFor` value; in
standalone mode, we need a source for this data (user meta field, plugin setting, or
adapter from the multi-author plugin's profile fields).

### Schemamap author entity vs. normalized author object: field coverage

Yoast's schemamap `Person` nodes and byline-feed's normalized author object cover overlapping
but non-identical fields. Full comparison table is in
`docs/research/current/nlweb-yoast-context.md` § "Field coverage."

Summary of what each source uniquely provides:

**Yoast has, we do not (yet):**
- Stable graph `@id` URIs anchoring each entity across the schema graph.
- `worksFor` / organizational affiliation from Yoast's Knowledge Graph settings.

**We have, Yoast does not:**
- `bylineRole` — typed contributor role (`creator`, `editor`, `guest`, `staff`, `contributor`, `bot`).
- `bylinePerspective` — per-post editorial signal (`reporting`, `opinion`, `analysis`, `sponsored`, and 8 others).
- Live `sameAs` data from multi-author plugin profile fields and fediverse handles
  (Yoast sources `sameAs` from its SEO settings UI, not from Co-Authors Plus or PublishPress).
- `aiTrainingConsent` — per-author and per-post AI rights signal (WP-06).
- Correct multi-author `Article.author` array (Yoast outputs single-author reference).

The integration strategy for Mode A (Yoast active) is to merge our data into Yoast's graph —
enriching `sameAs` rather than overwriting it, adding `additionalProperty` nodes for role
and consent signals, and replacing the single-author `Article.author` reference with our
full ordered array. **This strategy is implemented and live-verified with Yoast SEO 27.2 (2026-03-20).**

---

## Rights and AI consent

### Initial advisory signals shipped

**Status: WP-06 mostly implemented (2026-03-20)**

The following rights signals are implemented and tested:

- Per-author consent stored in `byline_feed_ai_consent` user meta (`allow`/`deny`/empty).
- Per-post consent override stored in `_byline_ai_consent` post meta.
- Consent resolution: post override wins; if unset, most-restrictive author preference wins (deny > allow > empty).
- Denied posts emit `<meta name="robots" content="noai, noimageai">`.
- Denied posts emit `TDMRep` header pointing to `/ai.txt`.
- Dynamic `/ai.txt` endpoint with filterable content.
- Classic editor metabox for per-post consent.
- User profile field for per-author consent.
- All of the above covered by PHPUnit tests.

### Remaining WP-06 gaps

- ~~**Feed-level rights metadata.**~~ ✅ Resolved (2026-03-20). RSS2 and Atom now emit `<byline:rights consent="deny" policy="..."/>` on denied items. JSON Feed emits `_byline.rights` with consent and policy fields. Live-verified on `single-instance.local`.
- ~~**Block editor consent UI.**~~ ✅ Resolved (2026-03-20). `ai-consent-panel.tsx` provides a `PluginDocumentSettingPanel` for per-post AI consent override, matching the perspective panel UX pattern.
- ~~**Consent audit logging.**~~ ✅ Resolved (2026-03-21). Consent changes are now recorded in an admin-only audit log with actor, timestamp, old value, and new value.
- ~~**Schema-level consent signals.**~~ ✅ Resolved (2026-03-20). `get_person_schema()` now includes `aiTrainingConsent` as `additionalProperty` on Person nodes across all three schema modes.

---

## Byline Feed plugin — integration boundaries

### ActivityPub plugin: `attributedTo` is out of scope

**Status:** Open — external dependency

**Affects:** WP-04 (`fediverse:creator`), WP-05 (JSON-LD), normalized author object `ap_actor_url` field

The Byline Feed plugin outputs per-author ActivityPub identity signals — `fediverse:creator` meta tags and `ap_actor_url` in `sameAs` — but does not and cannot influence what appears in `attributedTo` on federated post objects. That field is owned by the ActivityPub plugin (Matthias Pfefferle / Automattic), which currently assigns a single actor to each federated object.

**The specific gap:** On a multi-author post where two of three co-authors have AP actors, Byline Feed will emit correct `fediverse:creator` tags and `sameAs` entries for all three. The AP plugin's federated `Article` object will still reflect only one `attributedTo` actor. No hook currently exists in the AP plugin to accept multi-author attribution data from an external source.

**What we output vs. what AP owns:**

| Signal | Owner |
|---|---|
| `fediverse:creator` meta tags (per author) | Byline Feed |
| `sameAs` AP actor URLs in JSON-LD | Byline Feed |
| `ap_actor_url` field in normalized author object | Byline Feed |
| `attributedTo` on federated AP object | ActivityPub plugin |
| Actor JSON, HTTP Signatures, WebFinger resolution | ActivityPub plugin |

**Future path:** Resolving this gap requires either a filter hook from the AP plugin that accepts an array of actor URLs for `attributedTo`, or a coordinated FEP addressing multi-author attribution (the pre-FEP referenced in `author-identity-vision.md` is the relevant upstream thread). This is a candidate for an upstream conversation with the AP plugin maintainers, framed as an interface request rather than a bug report.

**For now:** Document in user-facing plugin notes that `fediverse:creator` tags reflect all attributed authors, but fediverse feed display of co-authors depends on the AP plugin and Mastodon's evolving multi-author support.

### Normalized author object: `ap_actor_url` and `did` field semantics

The normalized author object contract (see `implementation-spec.md`) includes `ap_actor_url` as a first-class field from WP-04 onward and reserves `did` for post-MVP WP-07 work. Three constraints apply across all adapters:

1. The `profiles` array MUST NOT be used as a substitute for `ap_actor_url` — the AP actor URL is cryptographically meaningful in a way that social profile links are not.
2. Guest authors (`is_guest: true`) MUST return empty string for `ap_actor_url` — they have no domain-anchored AP identity.
3. The `did` field MUST return empty string in all adapters until WP-07 ships. The `id` field carries neither AP actor nor DID semantics. See `author-identity-vision.md § did:web: as post-MVP bridge` for rationale.

---

## Source-level issues (byline-feed)

These are code-level findings from the full source review. See
`implementation-strategy/code-review-plan.md` for the complete prioritized backlog.

### P1 — Moderate issues (resolved)

All four P1 items were resolved in the current codebase prior to the 2026-03-20 review:

- **`feed-json.php`:** ✅ `get_posts()` now includes `'post_type' => get_post_types( array( 'public' => true ) )` (line 226).
- **`fediverse.php`:** ✅ The double normalization (lines 59 and 72) is intentional — pre-filter normalization for the input value, post-filter normalization as a safety net for filtered values. Not a bug.
- **`feed-common.php`:** ✅ `mb_substr()` now specifies `'UTF-8'` encoding parameter (line 26).
- **`perspective.php`:** ✅ Uses `require_once` for asset file inclusion (line 173). Safe because `enqueue_block_editor_assets` fires once per request.

### P2 — Defensive improvements (resolved)

- **`feed-rss2.php` / `feed-atom.php`:** ✅ Null-check added: `if ( empty( $wp_query ) || empty( $wp_query->posts ) )`.
- **`namespace.php`:** ✅ Filter-then-revalidate ordering documented in docblock.

---

## Test coverage gaps (byline-feed)

See `implementation-strategy/code-review-plan.md` for the complete prioritized backlog.

### P1 — High priority (mostly resolved)

- **Feed output E2E tests.** ✅ `tests/e2e/feed-output.spec.js` now covers RSS2 namespace+contributors, Atom namespace, JSON Feed `_byline` extension, JSON-LD Article+Person schema on singular posts, and `fediverse:creator` meta tags. All 5 tests pass against a live Local site (single-instance.local, 2026-03-20).
- **Empty authors array.** ✅ Covered in `test-feed-rss2.php`, `test-feed-atom.php`, `test-feed-json.php`, and `test-schema.php` for no-author resolution cases.
- **PPA integration test parity.** ✅ Covered in `test-integration-ppa.php` and `test-adapter-ppa.php`, including guest authors, multi-author ordering, linked user meta, and term-meta precedence.

### P2 — Medium priority (mostly resolved)

- **JSON Feed filter coverage.** ✅ `test-feed-json.php` now exercises `byline_feed_json_author_extension` and `byline_feed_json_feed`.
- **Special characters in author fields.** ✅ Feed and schema tests now cover `<`, `&`, `"`, emoji, and CJK across RSS2, Atom, JSON Feed, and singular schema output.
- **Role mapping completeness.** ✅ `test-adapter-core.php` now covers the standard WordPress role set.
- **REST API meta round-trip.** ✅ `test-author-meta.php` now verifies registered author meta through real REST requests.
- **Rank Math coexistence tests.** Detection exists in code but dedicated coexistence tests are thin.

---

## Pre-1.0 spec alignment

**Status:** Open — requires upstream conversation with Byline spec author

These are known structural divergences between the byline-feed implementation and the
pre-1.0 Byline spec that should be resolved before the plugin claims stable spec
conformance:

- **Multi-author-per-item structure.** The Byline spec's item-level author model and
  byline-feed's `<byline:author ref="..."/>` pattern may not be structurally aligned.
- **JSON Feed extension structure.** The `_byline` extension object shape may diverge
  from what the spec author expects.
- **Terminology drift.** `organization` / `publication` / `publisher` naming is not
  settled between the spec and the implementation.

---

## Compatibility

### WordPress version

Authorship plugin header declares `Requires at least: 5.4`, tested up to 6.2. The 6.2 cap is stale — the plugin likely works with current WordPress but testing has not been updated.

byline-feed CI tests against WordPress 6.0 through latest.

### PHP version

Authorship requires PHP 7.2+. Tooling (PHPCS, PHPStan) is pinned to PHP 7.4 and does not run on PHP 8.5 without deprecation suppression.

byline-feed CI tests across PHP 7.4–8.3.

### Multisite

The Authorship plugin has multisite-specific tests and uses `'blog_id' => 0` in `get_users()` calls to search across all sites. Guest authors created on one site exist in the shared `wp_users` table.

### Theme compatibility

Authorship intercepts `the_author`, author query vars, and capability checks transparently. Themes that use standard WordPress template tags (`the_author()`, `get_the_author()`, author archive templates) will work. Themes that read `$post->post_author` directly may show stale data (see `post_author` divergence above).

### Plugin compatibility

Co-Authors Plus and PublishPress Authors both use the `author` taxonomy slug. Authorship uses `authorship`. These should not conflict if multiple plugins are active, though running multiple multi-author plugins simultaneously is not recommended. Authorship provides WP-CLI migration commands for both CAP and PPA data.

### Yoast SEO compatibility

**Status:** ✅ Implemented and live-verified with Yoast SEO 27.2

byline-feed enriches Yoast's schema graph via `wpseo_schema_article` filter, replacing
the single-author `@id` reference with full multi-author Person objects including
`bylineRole`, `aiTrainingConsent`, fediverse `sameAs`, and `bylinePerspective`. Standalone
output is suppressed when Yoast is active — no duplicate JSON-LD.

### Rank Math compatibility

**Status:** ✅ Implemented (unit-tested, not yet live-verified with Rank Math installed)

`schema-rankmath.php` hooks `rank_math/json_ld` to enrich Article nodes with multi-author
Person arrays and `bylinePerspective`. Standalone output is suppressed when Rank Math is
active.
