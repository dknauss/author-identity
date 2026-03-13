# Project Assessment — Byline Feed Plugin

## Executive summary

The Byline Feed plugin addresses a genuine gap: ~40,000 WordPress sites using multi-author plugins produce feeds with no structured author identity. The plugin uses an adapter pattern to normalize author data from Co-Authors Plus, PublishPress Authors, or core WordPress, then routes that data into RSS2/Atom feeds (via the Byline XML namespace), HTML head (JSON-LD, fediverse:creator), HTTP headers (TDM-Rep), and well-known files (ai.txt).

The MVP (WP-01/02/03) — adapter layer, feed output, and perspective meta field — is substantially complete with CI, adapter tests, feed tests, and contract validation all in place. Post-MVP components (fediverse:creator, JSON-LD, AI consent) are 0% implemented but fully specified. Pre-WP-04 refinements (code deduplication, filter naming, Atom role test, author meta save test) are documented and ready to execute.

## Scope and key components

- **Adapter layer:** Detects active multi-author plugin, normalizes author data into a common object contract. Three adapters implemented (Core, CAP, PPA); two planned (Molongui, HM Authorship).
- **Feed output:** RSS2 and Atom feeds enriched with `xmlns:byline` namespace, per-feed contributor blocks, per-item author refs, roles, and perspective.
- **Perspective meta field:** Per-post editorial intent (reporting, analysis, satire, etc.) with block editor panel and classic metabox.
- **fediverse:creator output (planned):** HTML meta tags for Mastodon author attribution.
- **JSON-LD schema output (planned):** Multi-author Article + Person structured data.
- **AI consent and rights (planned):** Per-author/per-post training consent, TDM headers, ai.txt generation.

## Data flows

```
Author data source (CAP / PPA / Core WP)
    ↓ adapter.get_authors( $post )
Normalized author array (id, display_name, role, profiles, fediverse, ai_consent, ...)
    ↓ byline_feed_get_authors( $post ) + byline_feed_authors filter
Output channels:
    → RSS2: xmlns:byline namespace, <byline:contributors>, <byline:author ref>
    → Atom: parallel Byline elements
    → HTML head: fediverse:creator meta tags (WP-04), JSON-LD schema (WP-05)
    → HTTP headers: TDM-Rep (WP-06)
    → Filesystem: ai.txt (WP-06)
```

## Current state

| Area | Status |
| --- | --- |
| Adapter layer (WP-01) | ~95% — Core, CAP, PPA adapters implemented and tested. Contract validation enforced. Author meta fields (profiles, now, uses) read from user meta. |
| Feed output (WP-02) | ~90% — RSS2 and Atom output with namespace, contributors, per-item refs, perspective. Profile/now/uses elements render. Feed layer code duplication (R-1) and filter naming (R-2) tracked as pre-WP-04 refinements. |
| Perspective field (WP-03) | ~95% — Meta registration, validation, filter, classic metabox, block editor panel (TSX builds in CI). Allowed values centralized in `Perspective\get_allowed_values()`. |
| fediverse:creator (WP-04) | 0% — adapter reads the meta key, no UI or output |
| JSON-LD schema (WP-05) | 0% — entirely absent |
| AI consent (WP-06) | 0% — adapter reads the meta key, no UI or output |
| CI/CD | **Operational** — GitHub Actions with PHPUnit matrix (PHP 7.4–8.3 × WP 6.0–latest), PHPCS, Node build. Pinned action SHAs, `permissions: contents: read`. |
| Documentation | Strong vision/research/strategy docs. Consumer-facing output reference still missing. |

## Key risks

1. **Adapter drift.** CAP and PPA adapters were written against API contracts, not tested against real plugin installations. If those plugins change return shapes, the adapters fail silently.
2. **Scope creep.** The vision document covers C2S ActivityPub, C2PA, XFN harvesting, and a cross-plugin author API. The [scope boundaries](Implementation%20Strategy/implementation-spec.md#explicitly-not-in-scope) list explicitly defers these, but the temptation to expand is real.
3. **WP-06 complexity.** The consent resolution logic (most-restrictive-wins, post-level override, retroactive changes, audit logging) is the most complex state management in the plugin.
4. **External dependency on Gate C.** Feed-level rights metadata requires reader-side Byline parsing interest, which is externally dependent.

## Recommendations

1. ~~**Ship CI before writing more code.**~~ Done — CI pipeline operational with PHPUnit matrix, PHPCS, and Node build.
2. ~~**Complete WP-01 tests first.**~~ Done — all three adapter test files plus contract validation tests exist and pass.
3. ~~**Build the TSX.**~~ Done — `npm run build` runs in CI; perspective panel compiles.
4. **Resolve pre-WP-04 refinements.** Extract shared `output_person()` (R-1), decide Atom filter naming (R-2), add Atom role test (R-3), add author meta save test (R-4). See [implementation spec refinements](Implementation%20Strategy/implementation-spec.md#pre-wp-04-refinements).
5. **Hold the scope boundary.** The "not in scope" list exists for a reason. Resist adding WP-04/05/06 until Gate A is clean.
6. **Create consumer output reference.** Annotated feed examples with filter reference and field mapping — needed for adoption.

## Related documents

- [Implementation Strategy/implementation-spec.md](Implementation%20Strategy/implementation-spec.md) — Full spec, work packages, delivery schedule
- [Implementation Strategy/gap-analysis.md](Implementation%20Strategy/gap-analysis.md) — Detailed gap audit
- [TEST_COVERAGE_MATRIX.md](TEST_COVERAGE_MATRIX.md) — Test coverage status by domain
- [TDD_TESTING_STANDARD.md](TDD_TESTING_STANDARD.md) — Testing protocol
- [author-identity-vision.md](author-identity-vision.md) — Vision document
