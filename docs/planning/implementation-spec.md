# Byline Feed Plugin — Implementation Spec and Roadmap

## Plugin identity

- **Working name:** `byline-feed` — ship MVP under this name, earn a broader name through delivered scope (see [naming decision](../../Implementation%20Strategy/implementation-spec.md#naming-decision-pre-mvp))
- **Type:** WordPress plugin, distributed via wp.org
- **License:** GPLv2 or later
- **PHP floor:** 7.4
- **WP floor:** 6.0
- **Dependencies:** None (all multi-author plugin integrations are optional adapters)
- **Multi-author landscape:** See [multi-author-matrix.md](../research/multi-author-matrix.md) for a comparison of the systems this plugin adapts
- **Protocol landscape:** See [protocol-coverage-map.md](../research/protocol-coverage-map.md) for how the output protocols (Byline, JSON-LD, fediverse:creator, TDM-Rep) relate to each other
- **Scope boundaries:** See [explicitly not in scope](../../Implementation%20Strategy/implementation-spec.md#explicitly-not-in-scope) for what the vision discusses but the plugin deliberately defers

## Architectural overview

The plugin has three layers:

```
┌─────────────────────────────────────────────────┐
│                  Output Layer                    │
│  RSS2 · Atom · JSON-LD · fediverse:creator ·    │
│  TDM headers · ai.txt · rights metadata         │
├─────────────────────────────────────────────────┤
│              Normalized Author API               │
│  byline_feed_get_authors( WP_Post ) : array     │
│  Returns ordered array of author objects         │
├─────────────────────────────────────────────────┤
│                 Adapter Layer                    │
│  Co-Authors Plus · PublishPress Authors ·        │
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
    'role'         => string,  // Byline role: 'creator','editor','guest','staff','contributor','bot'.
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
Priority 1: Co-Authors Plus     — function_exists( 'get_coauthors' )
Priority 2: PublishPress Authors — function_exists( 'publishpress_authors_get_post_authors' )
                                   OR class_exists( 'MultipleAuthors\\Classes\\Objects\\Author' )
Priority 3: Molongui Authorship  — class_exists( 'Molongui\\Authorship\\Author' )
Priority 4: HM Authorship        — function_exists( 'Authorship\\get_authors' )
Priority 5: Core WordPress        — always available (fallback)
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

Detailed scaffolds for each: `docs/spec/work-packages/wp-01.md` through `wp-06.md`.

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
| Author denies AI training | Any | 1 user, consent=deny | unset | `noai` meta tag, TDM header |

## File structure

```
byline-feed/
├── byline-feed.php                    # Plugin bootstrap, adapter detection
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

The following concerns span multiple work packages. They are not separate deliverables — they are quality dimensions that apply throughout. For the full detail on each, see [Implementation Strategy/implementation-spec.md](../../Implementation%20Strategy/implementation-spec.md#cross-cutting-concerns).

### 1. Continuous integration

**Applies to:** All work packages. The plugin has no CI pipeline. A GitHub Actions workflow running PHPUnit (across a PHP 7.4–8.3 / WP 6.0–latest matrix), PHPCS, and the `@wordpress/scripts` build is the single highest-priority infrastructure gap. CI must exist before further work packages are developed. Separate integration test jobs should install Co-Authors Plus and PublishPress Authors as test dependencies.

### 2. Adapter validation against real plugins

**Applies to:** WP-01. The CAP and PPA adapters were written against those plugins' public API contracts but have not been tested against actual installations. CI integration test jobs that install specific plugin versions and run adapter/feed tests against real data are the most valuable testing investment. Edge cases: mixed user/guest author sets, author ordering, missing data fields, 5+ co-author posts, and plugin version drift.

### 3. Adapter contract enforcement

**Applies to:** WP-01. The `Adapter` interface's prose contract (the normalized author object shape) is not enforced in code. A validation function in `byline_feed_get_authors()` that checks required fields and applies zero-value defaults for optional fields — using `_doing_it_wrong()` in debug mode — catches malformed adapter output before it reaches feed/schema/meta output layers. This is a hardening pass, not a separate work package.

### 4. Feed output validation against the Byline spec

**Applies to:** WP-02. Current feed tests check XML well-formedness and structural expectations but do not validate against the Byline specification itself. Structural spec conformance tests (required attributes and children on Byline elements, vocabulary validation for roles and perspectives, omission vs. empty-element handling), and a round-trip test (parse generated XML back into author objects, verify they match input) catch encoding, escaping, and structural errors.

### 5. Consumer documentation — output reference

**Applies to:** Adoption strategy. The plugin needs a consumer-facing output reference (`byline-feed/docs/output-reference.md`) with annotated RSS2, Atom, JSON-LD, and HTML head examples showing every element the plugin produces and how to customize each via filters. This is separate from contributor docs (`CONTRIBUTING.md` with dev environment setup, test commands, and adapter development guide).

---

## Delivery schedule

For full ETA table, milestone timeline, and caveats, see [Implementation Strategy/implementation-spec.md § Delivery schedule](../../Implementation%20Strategy/implementation-spec.md#delivery-schedule).

Summary: ~3.5 weeks to wp.org submission (Gate A), ~7–8 weeks total for all work packages through Gate B'.

## Gap analysis

For a point-in-time audit of what exists vs. what the specs require, see [Implementation Strategy/gap-analysis.md](../../Implementation%20Strategy/gap-analysis.md). Key findings: 11 gaps identified (3 critical, 5 spec divergences, 3 structural), with resolution priorities mapped to work packages.
