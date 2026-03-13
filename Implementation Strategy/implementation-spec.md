# Byline Feed Plugin — Implementation Spec and Roadmap

## Plugin identity

- **Working name:** `byline-feed` (see naming decision below)
- **Type:** WordPress plugin, distributed via wp.org
- **License:** GPLv2 or later
- **PHP floor:** 7.4
- **WP floor:** 6.0
- **Dependencies:** None (all multi-author plugin integrations are optional adapters)

### Naming decision (pre-MVP)

The working name "Byline Feed" accurately describes WP-01 through WP-03 (feed output), but scopes too narrowly once WP-04 (fediverse:creator), WP-05 (JSON-LD schema), and WP-06 (AI consent) ship. The [vision document's naming section](../docs/vision/author-identity-vision.md#naming-and-positioning) frames the eventual positioning as "structured author identity and content provenance for WordPress" — but launching on wp.org under a name that broad invites scope confusion.

**Resolve before wp.org submission.** Options:

1. **Ship as "Byline Feed"**, rebrand later if post-MVP components justify it. Low risk, easy to change. The wp.org slug (`byline-feed`) is permanent, but the display name is not.
2. **Ship as "Byline"** (broader scope implied, still grounded in the spec name). Risk: namespace collision with the abandoned "Byline" plugin on wp.org.
3. **Ship as "Byline Identity"** or **"Author Identity"** — names the vision, but overpromises at MVP.

Recommendation: option 1. Ship the MVP under "Byline Feed," earn the broader name through delivered scope.

## Architectural overview

The plugin has three layers:

```
┌─────────────────────────────────────────────────┐
│                  Output Layer                   │
│  RSS2 · Atom · JSON-LD · fediverse:creator ·    │
│  TDM headers · ai.txt · rights metadata         │
├─────────────────────────────────────────────────┤
│              Normalized Author API              │
│  byline_feed_get_authors( WP_Post ) : array     │
│  Returns ordered array of author objects        │
├─────────────────────────────────────────────────┤
│                 Adapter Layer                   │
│  Co-Authors Plus · PublishPress Authors ·       │
│  Molongui · HM Authorship · Core WP fallback    │
└─────────────────────────────────────────────────┘
```

All output channels consume the same normalized author array. All adapters produce the same normalized author array. The adapter layer is the load-bearing abstraction.

## Normalized author object contract

Every adapter MUST return an ordered array of objects conforming to this shape:

```php
(object) [
    // Required
    'id'           => string,  // Unique within feed. Slug or prefixed numeric ID.
    'display_name' => string,  // Author's display name.

    // Optional identity
    'description'  => string,  // Bio. Output layer caps at 280 chars for Byline context.
    'url'          => string,  // Primary URL (personal site, about page).
    'avatar_url'   => string,  // Avatar image URL.
    'user_id'      => int,     // WordPress user ID if available, 0 if guest-only.

    // Optional role/type
    'role'         => string,  // Byline role: 'creator','editor','guest','staff','founder','contributor','bot'.
    'is_guest'     => bool,    // Whether this is a guest author.

    // Optional extended identity (progressive enhancement)
    'profiles'     => array,   // Array of ['href' => string, 'rel' => string].
    'now_url'      => string,  // /now page URL.
    'uses_url'     => string,  // /uses page URL.
    'fediverse'    => string,  // Fediverse handle (@user@instance).

    // Optional rights
    'ai_consent'   => string,  // 'allow', 'deny', or '' (unset).
]
```

Fields not available from a given adapter MUST be set to empty string, empty array, 0, or false as appropriate. The output layer MUST NOT assume any optional field is populated.

## Adapter detection and priority

On `plugins_loaded`, the plugin checks for active multi-author plugins and loads the first matching adapter:

```
Priority 1: Co-Authors Plus      — function_exists( 'get_coauthors' )
Priority 2: PublishPress Authors — function_exists( 'publishpress_authors_get_post_authors' )
                                   OR function_exists( 'get_post_authors' )
                                   OR class_exists( 'MultipleAuthors\\Classes\\Objects\\Author' )
Priority 3: Molongui Authorship  — class_exists( 'Molongui\\Authorship\\Author' )
Priority 4: HM Authorship        — function_exists( 'Authorship\\get_authors' )
Priority 5: Core WordPress       — always available (fallback)
```

The active adapter is filterable: `apply_filters( 'byline_feed_adapter', $adapter_instance )`.

## Work package sequence

Each work package is a self-contained deliverable. Packages are ordered by dependency — each builds on the previous. Work packages 1-3 constitute the MVP for wp.org submission.

| WP | Name | Depends on | Deliverable |
| --- | --- | --- | --- |
| 01 | Plugin scaffold and adapter interface | — | Plugin activates, detects multi-author plugin, returns normalized authors |
| 02 | Byline RSS2 and Atom output | 01 | Feeds include valid Byline namespace, contributors, per-item author refs |
| 03 | Perspective meta field and editor UI | 01, 02 | Editors set perspective per post, appears in feed output |
| 04 | `fediverse:creator` meta tag output | 01 | Shared links on Mastodon show author bylines |
| 05 | JSON-LD schema output | 01 | Multi-author Article + Person schema in `wp_head` |
| 06 | Content rights and AI consent | 01 | Per-author/per-post consent, TDM headers, `ai.txt` |

Detailed scaffolds for each: `Implementation Strategy/wp-01.md` through `Implementation Strategy/wp-06.md`.

## Scope philosophy: one plugin, gated growth

All six work packages ship inside a single plugin. The adapter layer is the product — it normalizes author data once and routes it to every output channel. Splitting outputs into separate plugins would force users to install multiple things to solve one problem ("my identity doesn't travel with my content") and create coordination overhead between plugins that share the same data source. The single-source-of-truth principle demands a single plugin.

However, scope expansion must be earned, not assumed. The Byline feed output (WP-01/02/03) is the thesis — it validates the adapter architecture and tests spec adoption. The other output channels (fediverse:creator, JSON-LD, AI consent) are the hedge — they deliver value through channels that already have deployed consumers (Mastodon, Google, AI crawlers) regardless of whether Byline gains feed reader adoption. If Byline never gets reader traction, the plugin still does four useful things.

The gates below prevent broader scope from diluting MVP execution while recognizing that not every output channel depends on Byline spec adoption.

## Release gates

```
Gate A ─── MVP quality ──────────────────────────────────────────────
           WP-01/02/03 merged, feed XML valid, compatibility
           verified on core + CAP + PPA.

Gate B ─── Real-world adoption ──────────────────────────────────────
           Production feeds emitting valid Byline data, early
           operator feedback collected.

Gate B' ── Adapter-proven expansion (after B) ───────────────────────
           WP-04 (fediverse:creator) and WP-05 (JSON-LD schema).
           These output channels have deployed infrastructure
           already consuming their signals — Mastodon parses
           fediverse:creator tags today, Google parses schema.org
           today. They validate the adapter architecture across
           new output formats without any dependency on Byline
           spec adoption by feed readers.
           WP-06 HTML/header signals (nosnippet, TDM headers,
           robots.txt tokens, ai.txt) also ship here — they work
           independently of feed reader support.

Gate C ─── Reader-side signal ───────────────────────────────────────
           At least one feed reader maintainer indicates
           implementation interest or parser experimentation.

Gate D ─── Feed-level rights metadata (after C) ─────────────────────
           WP-06 feed integration (`cc:license` for explicit license
           declarations, plus any dedicated deny-policy extension)
           ships here. Feed-level rights metadata only has value
           when readers parse Byline data — unlike the
           HTML/header signals that work independently.
```

**Rationale for splitting Gate D.** The original four-gate sequence treated WP-04/05/06 as a single block gated on reader-side signal. But WP-04 and WP-05 don't depend on Byline spec adoption at all:

- **WP-04 (fediverse:creator):** Mastodon's author attribution feature launched July 2024 specifically for journalism. The infrastructure is deployed and waiting for publishers to emit the tags. No feed reader involvement needed.
- **WP-05 (JSON-LD schema):** Google already parses Article + Person schema. Multi-author schema support across the WordPress ecosystem is weak. This delivers E-E-A-T value on day one.
- **WP-06 (HTML/header signals):** `nosnippet`, `tdm-reservation`/`tdm-policy` headers, `robots.txt` token rules, and `ai.txt` all operate through HTTP and HTML mechanisms that crawlers already understand. They don't need feed reader support.
- **WP-06 (feed-level rights):** Feed-level rights metadata (`cc:license` for explicit license declarations and any dedicated deny-policy extension) is the piece that genuinely depends on readers parsing Byline data. It gates on C.

Waiting for reader-side signal before shipping fediverse:creator tags and JSON-LD schema would mean sitting on proven infrastructure to protect against a risk (Byline spec adoption) that those features don't share. The split gates let the plugin grow its value proposition as the adapter layer proves out, while feed-level features still wait for their specific dependency.

## Explicitly not in scope

The [vision document](../docs/vision/author-identity-vision.md) discusses capabilities that the plugin deliberately does not attempt. This list prevents scope creep by naming the boundaries:

- **ActivityPub C2S publishing.** The vision explores Client-to-Server ActivityPub as a future publication protocol. The plugin does not implement C2S. The adapter pattern is designed so a C2S output channel could be added, but no work package targets it.
- **C2PA content provenance.** The protocol coverage map lists C2PA (Coalition for Content Provenance and Authenticity) as a content-authenticity standard. The plugin does not generate or verify C2PA manifests. C2PA operates at the media-asset level (images, video), not at the article-metadata level where this plugin works.
- **XFN blogroll harvesting.** The vision's Relationships section discusses XFN-annotated blogrolls as a machine-readable social graph. The plugin does not read or import XFN data from WordPress links. It emits `rel="me"` semantics on profile links it already outputs, but does not become an XFN management tool.
- **Cross-plugin normalized author API as a standalone library.** The adapter layer normalizes author data for this plugin's output channels. It is not a general-purpose multi-author abstraction layer for the WordPress ecosystem. Third-party plugins can use the `byline_feed_get_authors()` function and filters, but the API is designed for this plugin's consumers, not as a universal author resolution standard.
- **Guest author management.** The plugin does not create, edit, authenticate, or manage guest authors. It reads them from whatever multi-author system is active. Security concerns about guest author login, password reset, and capability mapping belong to the upstream multi-author plugins (CAP, PPA, HM Authorship), not to this plugin.
- **Full social graph / relationship management.** The plugin emits relationship metadata (co-authorship, organizational affiliation, profile links) in its output channels. It does not maintain a relationship database, import social graph data, or replicate IndieWeb social reader functionality.

## Filter and hook API

The plugin exposes the following public API for theme and plugin developers:

### Functions

- `byline_feed_get_authors( WP_Post $post ) : array` — returns normalized author array for the given post.
- `byline_feed_get_perspective( WP_Post $post ) : string` — returns the perspective value for the given post (from meta or filter).

### Filters

- `byline_feed_adapter` — override the auto-detected adapter instance.
- `byline_feed_authors` — modify the normalized author array after adapter resolution. Receives `( $authors, $post )`.
- `byline_feed_role` — override role mapping per author per post. Receives `( $role, $author_object, $post )`.
- `byline_feed_perspective` — compute or override perspective. Receives `( $perspective, $post )`.
- `byline_feed_person_xml` — modify the XML output for a `byline:person` element. Receives `( $xml, $author_object )`.
- `byline_feed_item_xml` — modify the XML output for per-item Byline elements. Receives `( $xml, $post, $authors )`.
- `byline_feed_schema_person` — modify the JSON-LD Person object. Receives `( $person_array, $author_object )`.
- `byline_feed_ai_consent` — override AI consent per author per post. Receives `( $consent, $author_object, $post )`.
- `byline_feed_fediverse_handle` — override fediverse handle per author. Receives `( $handle, $author_object )`.

### Actions

- `byline_feed_after_rss2_contributors` — fires after the `<byline:contributors>` block is output in RSS2 head.
- `byline_feed_after_rss2_item` — fires after per-item Byline elements are output.

## Test strategy

### Unit tests

Each adapter gets its own test class mocking the upstream plugin's API functions. Tests verify:

- Correct normalized object shape for each adapter.
- Empty/missing field handling.
- Guest author detection.
- Role mapping.
- Author ordering preservation.

### Integration tests

Require the actual multi-author plugin to be active (via WP test suite's plugin loading):

- CAP installed → feed output includes Byline elements for co-authored posts.
- PPA installed → feed output includes Byline elements for posts with multiple PPA authors.
- No multi-author plugin → feed output includes Byline elements from core `post_author`.

### Feed validation

After each feed output test, validate the XML against:

- Well-formedness (XML parsing succeeds).
- Byline namespace is declared.
- Standard `<author>` / `<dc:creator>` elements are still present alongside Byline elements.
- `byline:person` elements have required `id` and `name` children.

### Testing matrix

| Scenario | Adapter | Authors | Perspective | Expected |
| --- | --- | --- | --- | --- |
| Single author, no plugin | Core | 1 user | unset | One `byline:person`, no `byline:perspective` |
| Single author, perspective set | Core | 1 user | `analysis` | One `byline:person`, `byline:perspective` = `analysis` |
| Two co-authors via CAP | CAP | 1 user + 1 guest | unset | Two `byline:person` refs, guest has `role=guest` |
| Three authors via PPA | PPA | 2 users + 1 guest | `reporting` | Three refs, correct order, perspective present |
| Author with fediverse handle | Any | 1 user with meta | unset | `fediverse:creator` tag in `wp_head` |
| Author denies AI training | Any | 1 user, consent=deny | unset | AI crawler policy signal + TDM policy header |

## File structure

```
byline-feed/
├── byline-feed.php                     # Plugin bootstrap, adapter detection
├── composer.json
├── package.json
├── readme.txt                          # wp.org readme
│
├── inc/
│   ├── namespace.php                   # Public API functions, hook registration
│   ├── interface-adapter.php           # Adapter interface definition
│   ├── class-adapter-core.php          # Core WordPress fallback adapter
│   ├── class-adapter-cap.php           # Co-Authors Plus adapter
│   ├── class-adapter-ppa.php           # PublishPress Authors adapter
│   ├── class-adapter-molongui.php      # Molongui Authorship adapter
│   ├── class-adapter-authorship.php    # HM Authorship adapter
│   ├── feed-rss2.php                   # RSS2 Byline output hooks
│   ├── feed-atom.php                   # Atom Byline output hooks
│   ├── schema.php                      # JSON-LD output
│   ├── fediverse.php                   # fediverse:creator meta tag output
│   ├── rights.php                      # AI consent, TDM headers, ai.txt
│   └── perspective.php                 # Perspective meta field registration
│
├── src/
│   └── perspective-panel.tsx           # Block editor sidebar for perspective
│
└── tests/
    └── phpunit/
        ├── test-adapter-core.php
        ├── test-adapter-cap.php
        ├── test-adapter-ppa.php
        ├── test-feed-rss2.php
        ├── test-feed-atom.php
        ├── test-schema.php
        ├── test-fediverse.php
        ├── test-rights.php
        └── test-perspective.php
```

## Acceptance criteria for wp.org submission (MVP)

Work packages 01 + 02 + 03 constitute the MVP:

1. Plugin activates without errors on PHP 7.4+ / WP 6.0+.
2. Plugin auto-detects Co-Authors Plus, PublishPress Authors, or falls back to core.
3. RSS2 feeds include valid `xmlns:byline` namespace declaration.
4. RSS2 feeds include `<byline:contributors>` with `<byline:person>` for each author in the feed.
5. RSS2 feed items include `<byline:author ref="..."/>` and `<byline:role>` for attributed authors.
6. Atom feeds include equivalent Byline elements.
7. Standard `<author>` and `<dc:creator>` elements are preserved (Byline is additive).
8. Perspective meta field is available in the block editor.
9. `byline:perspective` appears in feed output when set.
10. All filters are documented and functional.
11. PHPUnit test suite passes for all adapter and feed output scenarios.
12. No PHPCS violations against WordPress coding standards.
13. GitHub Actions CI passes on all supported PHP/WP matrix combinations.

---

## Cross-cutting concerns

The following concerns span multiple work packages. They are not separate deliverables — they are quality dimensions that apply throughout.

### 1. Continuous integration

**Applies to:** All work packages.

The plugin has no CI pipeline. This is the single highest-priority infrastructure gap. Without CI, every commit is a manual verification burden.

**GitHub Actions workflow** (`.github/workflows/ci.yml`):

- **PHP test matrix:** PHP 7.4, 8.0, 8.1, 8.2, 8.3 against WP 6.0, 6.4, latest.
- **PHPUnit:** Run via the WordPress test harness (`bin/install-wp-tests.sh`).
- **PHPCS:** WordPress coding standards check (already configured in `composer.json`).
- **Node build:** `npm ci && npm run build` to verify `perspective-panel.tsx` compiles.
- **Integration test jobs:** Separate CI jobs that install Co-Authors Plus and PublishPress Authors as test dependencies, then run the adapter and feed output tests against real plugin APIs (see § Adapter validation below).

The CI workflow should be created before any further work packages are developed. Every PR must pass CI. The workflow file is part of the plugin's infrastructure, not a work package deliverable — it enables everything else.

**File:** `.github/workflows/ci.yml`

### 2. Adapter validation against real plugins

**Applies to:** WP-01, and indirectly every work package that consumes adapter output.

The CAP and PPA adapters were written against those plugins' public API contracts as documented in their source code. They have not been tested against actual plugin installations. The `function_exists` detection is standard WordPress practice and won't break — but the data those functions *return* could differ between plugin versions, and the object shapes could change in updates.

**Integration test strategy:**

- **CI matrix jobs** (see § CI above) that install specific versions of CAP and PPA via Composer or WP-CLI before running tests:
  - Co-Authors Plus: latest stable from wp.org.
  - PublishPress Authors: latest stable from wp.org (free tier).
  - Neither installed: core fallback path.
- **Adapter edge-case tests** (expand `test-adapter-cap.php` and `test-adapter-ppa.php`):
  - Mixed author sets: WP users + guest authors in the same post.
  - Author ordering: verify the adapter preserves the order returned by the upstream plugin.
  - Missing data: guest authors with no bio, no avatar, no URL — verify all optional fields default to zero values.
  - Large author lists: 5+ co-authors (some newsroom posts have many contributors).
  - Plugin version drift: if CAP or PPA changes their return shapes, the test should fail early with a clear message, not produce silently wrong output.
- **Compatibility documentation:** A minimum supported version matrix for each adapted plugin, maintained in the plugin readme and updated when CI confirms compatibility.

**Files:** `tests/phpunit/test-adapter-cap.php`, `tests/phpunit/test-adapter-ppa.php` (expanded).

### 3. Adapter contract enforcement

**Applies to:** WP-01, and every adapter (current and future).

The `Adapter` interface defines `get_authors( WP_Post $post ): array` with a prose contract in the docblock. The normalized author object shape is documented in this spec but not enforced in code. A malformed author object (missing `id`, wrong type for `profiles`, unexpected `role` value) would pass through the adapter layer silently and produce broken feed output, invalid JSON-LD, or empty `fediverse:creator` tags.

**Enforcement approach:**

Add a validation function that every adapter's output passes through before reaching the output layer:

```php
function validate_author_object( object $author ): object {
    // Required fields — fail loudly if missing.
    if ( empty( $author->id ) || ! is_string( $author->id ) ) {
        _doing_it_wrong( __FUNCTION__, 'Author object missing required string "id" field.', '0.1.0' );
    }
    if ( empty( $author->display_name ) || ! is_string( $author->display_name ) ) {
        _doing_it_wrong( __FUNCTION__, 'Author object missing required string "display_name" field.', '0.1.0' );
    }

    // Optional fields — set to zero values if absent.
    $defaults = [
        'description' => '',
        'url'         => '',
        'avatar_url'  => '',
        'user_id'     => 0,
        'role'        => 'contributor',
        'is_guest'    => false,
        'profiles'    => [],
        'now_url'     => '',
        'uses_url'    => '',
        'fediverse'   => '',
        'ai_consent'  => '',
    ];
    foreach ( $defaults as $key => $default ) {
        if ( ! isset( $author->$key ) ) {
            $author->$key = $default;
        }
    }

    return $author;
}
```

This runs in `byline_feed_get_authors()` after the adapter returns and before the `byline_feed_authors` filter fires. In development/debug mode (`WP_DEBUG === true`), it emits `_doing_it_wrong` notices for missing required fields. In production, it silently applies defaults for optional fields so output never breaks.

This is not a separate work package — it's a hardening pass on WP-01's public API. Future adapter authors (Molongui, HM Authorship, or third-party) get immediate feedback when their adapter returns malformed data.

**Files:** `inc/namespace.php` (add validation), `tests/phpunit/test-adapter-contract.php` (new — tests that intentionally malformed objects are caught).

### 4. Feed output validation against the Byline spec

**Applies to:** WP-02 (RSS2/Atom output).

The existing feed tests check XML well-formedness and structural expectations (namespace present, contributors block exists, item refs match). They do not validate output against the Byline specification itself.

**Validation approach:**

- **Structural spec conformance:** Add test assertions that verify the feed output meets the Byline spec v0.1.0 requirements:
  - `<byline:person>` MUST have `id` attribute and `<byline:name>` child.
  - `<byline:author>` MUST have `ref` attribute matching a declared person's `id`.
  - `<byline:role>` MUST contain a value from the spec's role vocabulary.
  - `<byline:perspective>` MUST contain a value from the spec's perspective vocabulary.
  - Optional elements (`<byline:context>`, `<byline:url>`, `<byline:avatar>`, `<byline:profile>`) MUST be omitted (not empty) when the underlying data is absent.
- **Schema validation:** If bylinespec.org publishes an XSD or RelaxNG schema, add a test that validates the complete feed output against it. Until then, the structural assertions above serve as the programmatic equivalent.
- **Round-trip test:** Parse the generated feed XML back into author objects and verify they match the input. This catches encoding issues, escaping problems, and structural errors that pass well-formedness checks but lose data.

**Files:** `tests/phpunit/test-feed-rss2.php` (expanded), `tests/phpunit/test-feed-atom.php` (expanded).

### 5. Consumer documentation — output reference

**Applies to:** Adoption strategy, not a specific work package.

The plugin will live or die on adoption. Adoption depends on two audiences understanding the output: **feed reader developers** who need to parse Byline XML, and **theme/plugin developers** who need to extend or customize the output.

**Output reference document** (`byline-feed/docs/output-reference.md`):

- **Annotated RSS2 example:** A complete RSS2 feed with Byline namespace, showing every element the plugin can produce, with inline comments explaining each one.
- **Annotated Atom example:** The parallel Atom output.
- **JSON-LD example:** The Article + Person schema output from WP-05.
- **HTML head example:** The `fediverse:creator` and `robots`/TDM meta tags from WP-04 and WP-06.
- **Element reference table:** Every Byline element the plugin outputs, its source in the normalized author object, and which adapter fields populate it.
- **Filter reference:** How to customize each output element, with copy-paste code examples.

This is not a `CONTRIBUTING.md` (contributor onboarding can come later). It is a consumer-facing document that answers "what does this plugin produce and how do I use it?" — the question that determines whether anyone integrates with the output.

**File:** `byline-feed/docs/output-reference.md`

**Contributor quick-start** (`byline-feed/CONTRIBUTING.md`):

A shorter document covering:

- Local dev environment: PHP 7.4+, WordPress test suite setup, `composer install`, `npm ci`.
- How to run tests: `composer test`, `npm run build`.
- How to add a new adapter: implement the `Adapter` interface, add detection to `bootstrap()`, add test class.
- CI expectations: what the GitHub Actions workflow checks.

**File:** `byline-feed/CONTRIBUTING.md`

---

## Delivery schedule

Based on the [gap analysis](gap-analysis.md) — what exists, what's missing, and what the gaps require. Estimates assume one developer working on this as a focused project.

### ETA by deliverable

| Deliverable | Status | Remaining work | Estimate | Depends on |
| --- | --- | --- | --- | --- |
| **CI pipeline** | 0% | GitHub Actions workflow, phpunit.xml.dist, test bootstrap, bin/install-wp-tests.sh | 1–2 days | — |
| **WP-01 completion** | ~75% | Missing test files (CAP, PPA), role mapping alignment, adapter contract validation function | 3–4 days | CI |
| **WP-02 completion** | ~80% | Add profile/now/uses output, Atom filter parity, Atom tests, round-trip feed validation, additional RSS2 test scenarios | 2–3 days | WP-01 |
| **WP-03 completion** | ~90% | Deduplicate allowed-values list, update panel labels, run npm build, verify block editor panel loads | 1 day | WP-01, WP-02 |
| **Gate A: MVP quality** | — | All of the above, plus PHPCS pass, manual QA on WP 6.0+ with CAP and PPA | 1–2 days | WP-01/02/03 |
| **Output reference doc** | 0% | Annotated RSS2/Atom examples, element reference table, filter reference | 2–3 days | Gate A |
| **CONTRIBUTING.md** | 0% | Dev setup, test commands, adapter template | 1 day | CI |
| **wp.org submission** | — | Readme review, plugin check, screenshots, initial release | 1–2 days | Gate A |
| | | | | |
| **WP-04: fediverse:creator** | ~0% | Meta registration, user profile field, wp_head output, handle normalization, tests | 3–4 days | WP-01 |
| **WP-05: JSON-LD schema** | 0% | Article+Person schema, sameAs from profiles, Yoast/Rank Math detection, tests | 4–5 days | WP-01 |
| **WP-06: AI consent** | 0% | Per-author/per-post consent, resolution logic, HTML/header output, ai.txt, user/post UI, audit logging, tests | 6–8 days | WP-01 |
| **Gate B': adapter-proven expansion** | — | WP-04 + WP-05 + WP-06 HTML/header signals shipped | After Gate A | WP-04/05/06 |
| **Gate D: feed-level rights** | — | WP-06 feed integration (cc:license in items) | After Gate C | WP-06 |

### Milestone timeline

| Milestone | Cumulative estimate |
| --- | --- |
| **CI green + WP-01 complete** | ~1 week |
| **MVP code complete** (WP-01/02/03 + tests passing) | ~2.5 weeks |
| **wp.org submission ready** (Gate A + docs + submission prep) | ~3.5 weeks |
| **Post-MVP expansion** (WP-04/05/06) | +3–4 weeks after Gate A |
| **Full plugin** (all work packages, all gates through B') | ~7–8 weeks total |

### Caveats

- **Integration testing against real CAP/PPA installations** could surface adapter bugs that take unpredictable time to fix. The adapters were written against API contracts, not tested against real plugin behavior. Budget an extra 2–3 days for this.
- **WP-06 is the riskiest work package.** The consent resolution logic (most-restrictive-wins across co-authors, post-level override, retroactive consent changes, audit logging) has the most complex state management. The 6–8 day estimate assumes no scope surprises.
- **Gate C (reader-side signal)** is externally dependent and has no ETA. Feed-level rights metadata (Gate D) ships only after at least one feed reader indicates Byline parsing interest. This could be weeks or months.
- **Estimates do not include time for Byline spec validation tooling.** If bylinespec.org publishes an XSD or RelaxNG schema during development, integrating schema validation into the test suite would add 1–2 days.
- **Estimates assume the adapters work correctly against current CAP/PPA versions.** If either plugin has changed its return shapes since the adapters were written, debugging and updating could add 2–3 days per adapter.
