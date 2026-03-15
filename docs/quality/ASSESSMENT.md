# Project Assessment — Byline Feed Plugin

## Executive summary

The Byline Feed plugin addresses a real interoperability gap: WordPress sites with multiple authors often publish feeds that lose structured attribution. Gate A is now complete. The current plugin has the feed MVP foundation in place: adapter normalization, RSS2/Atom/JSON Feed Byline output, perspective support, PHPUnit coverage, PHPCS enforcement, and GitHub Actions CI.

The project is no longer deciding whether the MVP is viable. The main question is execution order after Gate A:

1. keep WP-05 maintained as a shipped output channel
2. add HM Authorship as the next adapter tranche
3. approach WP-06 with tighter scope discipline than the earlier feed work required

## Scope and key components

- **Adapter layer:** Detects Co-Authors Plus, PublishPress Authors, or core WordPress and normalizes author data into a common contract.
- **Feed output:** RSS2, Atom, and JSON Feed enriched with Byline metadata — feed-level contributor registries, item-level author refs, roles, and perspective.
- **Perspective meta field:** Per-post editorial intent with block editor support and feed output.
- **fediverse:creator output:** HTML meta tags for Mastodon-style author attribution on singular content. `ap_actor_url` is a supporting cross-cutting design field for this and WP-05, not a separate roadmap item.
- **JSON-LD schema output:** Multi-author Article + Person structured data on singular content, with conservative coexistence rules for known schema-owning SEO plugins.
- **AI consent and rights (planned):** Per-author/per-post training consent, TDM headers, and related output.

## Data flows

```text
Author data source (CAP / PPA / Core WP)
    ↓ adapter.get_authors( $post )
Normalized author array
    ↓ byline_feed_get_authors( $post ) + validation + filters
Output channels:
    → RSS2: Byline namespace, contributors, item author refs, roles, perspective
    → Atom: parallel Byline elements with equivalent filters
    → JSON Feed: _byline extension objects on authors and items
    → HTML head: fediverse:creator meta tags (WP-04, implemented)
    → HTML head / JSON-LD: Article + Person graph (WP-05, implemented)
    → HTTP headers / meta / files: rights and consent signals (WP-06, planned)
```

## Current state

| Area | Status |
| --- | --- |
| Adapter layer (WP-01) | Implemented, tested, and CI-verified |
| Feed output (WP-02) | Implemented for RSS2, Atom, and JSON Feed with automated coverage |
| Perspective field (WP-03) | Implemented, covered in PHPUnit and Playwright, and manually verified on the local Studio site |
| fediverse:creator (WP-04) | Implemented with PHPUnit coverage and user-profile field support |
| JSON-LD schema (WP-05) | Implemented with PHPUnit coverage and conservative Yoast/Rank Math coexistence |
| AI consent (WP-06) | Not started |
| CI/CD | Present and passing on supported PHP/WP matrix combinations |
| Documentation | Strong project/governance docs; consumer output reference exists and now covers feeds plus HTML-head outputs |

## Key risks

1. **Post-Gate-A scope drift.** The vision is broader than the active roadmap. Without discipline, WP-05/06 can turn into premature identity-framework work instead of focused output features.
2. **Upstream plugin drift.** CAP and PPA now have dedicated CI coverage against installed upstream plugins, but future adapter tranches (HM Authorship, Molongui if added) will need the same level of real-plugin validation.
3. **Unsupported-plugin behavior.** Live verification showed that sites using unsupported multi-author plugins can still have a mismatch between core author strings and Byline output. That is expected today, but it argues for explicit backlog tracking and a clean HM Authorship tranche.
4. **WP-06 complexity.** Rights and consent remain the most stateful and policy-sensitive part of the roadmap.
5. **Pre-1.0 spec divergence.** Multi-author item structure, JSON Feed structure, and terminology drift remain unresolved upstream issues.

## Recommendations

1. **Keep HM Authorship next.** It is the strongest next adapter tranche after WP-05 and should ship with real-plugin tests from the start.
2. **Keep WP-04/WP-05 maintenance factual and conservative.** The handle-based meta tags and JSON-LD schema now ship; deeper ActivityPub federation alignment still belongs to the separate upstream integration conversation.
3. **Keep UI hardening targeted.** The block-editor perspective path is now browser-covered; the remaining UI backlog is the fediverse profile field and classic-editor metabox fallback.
4. **Expand tests and docs in lockstep.** New output channels should land with their test files and consumer docs rather than being documented later.
5. **Keep using the new governance files.** `CHANGELOG.md`, `RELEASE_NOTES.md`, templates, and contributor guidance only matter if they become part of normal release practice.

## Related documents

- [Implementation Strategy/implementation-spec.md](../../Implementation%20Strategy/implementation-spec.md) — Full spec, work packages, and cross-cutting concerns
- [Implementation Strategy/gap-analysis.md](../../Implementation%20Strategy/gap-analysis.md) — Current remaining gaps
- [TEST_COVERAGE_MATRIX.md](./TEST_COVERAGE_MATRIX.md) — Test coverage status by domain
- [TDD_TESTING_STANDARD.md](./TDD_TESTING_STANDARD.md) — Testing protocol
- [author-identity-vision.md](../vision/author-identity-vision.md) — Vision document
