# Project Assessment — Byline Feed Plugin

## Executive summary

The Byline Feed plugin addresses a genuine gap: ~40,000 WordPress sites using multi-author plugins produce feeds with no structured author identity. The plugin uses an adapter pattern to normalize author data from Co-Authors Plus, PublishPress Authors, or core WordPress, then routes that data into RSS2/Atom feeds (via the Byline XML namespace), HTML head (JSON-LD, fediverse:creator), HTTP headers (TDM-Rep), and well-known files (ai.txt).

The MVP (WP-01/02/03) — adapter layer, feed output, and perspective meta field — is ~80% implemented. Post-MVP components (fediverse:creator, JSON-LD, AI consent) are 0% implemented but fully specified.

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
| Adapter layer (WP-01) | ~75% — code production-ready, 2 of 3 test files missing |
| Feed output (WP-02) | ~80% — code production-ready, Atom tests missing, 3 Byline elements not rendered |
| Perspective field (WP-03) | ~90% — code and tests complete, TSX never built, minor spec divergences |
| fediverse:creator (WP-04) | 0% — adapter reads the meta key, no UI or output |
| JSON-LD schema (WP-05) | 0% — entirely absent |
| AI consent (WP-06) | 0% — adapter reads the meta key, no UI or output |
| CI/CD | 0% — no phpunit.xml, no GitHub Actions, no test harness |
| Documentation | Strong vision/research docs; no consumer-facing output reference |

## Key risks

1. **Adapter drift.** CAP and PPA adapters were written against API contracts, not tested against real plugin installations. If those plugins change return shapes, the adapters fail silently.
2. **Scope creep.** The vision document covers C2S ActivityPub, C2PA, XFN harvesting, and a cross-plugin author API. The [scope boundaries](../../Implementation%20Strategy/implementation-spec.md#explicitly-not-in-scope) list explicitly defers these, but the temptation to expand is real.
3. **WP-06 complexity.** The consent resolution logic (most-restrictive-wins, post-level override, retroactive changes, audit logging) is the most complex state management in the plugin.
4. **External dependency on Gate C.** Feed-level rights metadata requires reader-side Byline parsing interest, which is externally dependent.

## Recommendations

1. **Ship CI before writing more code.** The test suite cannot run. This blocks everything.
2. **Complete WP-01 tests first.** The adapter layer is the foundation — it must be proven before feed output is trustworthy.
3. **Build the TSX.** The perspective panel has never been compiled. Verify it works in a real editor.
4. **Fix spec divergences before adding scope.** The role mapping signature, missing Byline elements, and duplicated allowed-values list are small fixes that prevent drift from compounding.
5. **Hold the scope boundary.** The "not in scope" list exists for a reason. Resist adding WP-04/05/06 until Gate A is clean.

## Related documents

- [Implementation Strategy/implementation-spec.md](../../Implementation%20Strategy/implementation-spec.md) — Full spec, work packages, delivery schedule
- [Implementation Strategy/gap-analysis.md](../../Implementation%20Strategy/gap-analysis.md) — Detailed gap audit
- [TEST_COVERAGE_MATRIX.md](TEST_COVERAGE_MATRIX.md) — Test coverage status by domain
- [TDD_TESTING_STANDARD.md](TDD_TESTING_STANDARD.md) — Testing protocol
- [author-identity-vision.md](../vision/author-identity-vision.md) — Vision document
