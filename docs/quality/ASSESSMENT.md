# Project Assessment — Byline Feed Plugin

## Executive summary

The Byline Feed plugin addresses a real interoperability gap: WordPress sites with multiple authors often publish feeds that lose structured attribution. Gate A is now complete. The current plugin has the feed MVP foundation in place: adapter normalization, RSS2/Atom/JSON Feed Byline output, perspective support, PHPUnit coverage, PHPCS enforcement, and GitHub Actions CI.

The project is no longer deciding whether the MVP is viable. The main question is execution order after the current adapter/output baseline:

1. keep WP-04 and WP-05 maintained as shipped output channels
2. start WP-06 without inflating it into a general-purpose policy framework
3. keep future adapter expansion disciplined after the HM Authorship tranche

## Scope and key components

- **Adapter layer:** Detects PublishPress Authors, HM Authorship, Co-Authors Plus, or core WordPress and normalizes author data into a common contract.
- **Feed output:** RSS2, Atom, and JSON Feed enriched with Byline metadata — feed-level contributor registries, item-level author refs, roles, and perspective.
- **Perspective meta field:** Per-post editorial intent with block editor support and feed output.
- **fediverse:creator output:** HTML meta tags for Mastodon-style author attribution on singular content. `ap_actor_url` is a supporting cross-cutting design field for this and WP-05, not a separate roadmap item.
- **JSON-LD schema output:** Multi-author Article + Person structured data on singular content, with conservative coexistence rules for known schema-owning SEO plugins.
- **AI consent and rights:** Initial per-author/per-post training consent, `robots` meta, `TDMRep` headers, and `ai.txt` now exist. Feed-level rights metadata, audit logging, and richer UI remain later WP-06 work.

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
    → HTTP headers / meta / files: rights and consent signals (WP-06, initial slice implemented)
```

## Current state

| Area | Status |
| --- | --- |
| Adapter layer (WP-01) | Implemented, tested, and CI-verified across core, CAP, PPA, and HM Authorship |
| Feed output (WP-02) | Implemented for RSS2, Atom, and JSON Feed with automated coverage |
| Perspective field (WP-03) | Implemented, covered in PHPUnit and Playwright, and manually verified on the local Studio site |
| fediverse:creator (WP-04) | Implemented with PHPUnit coverage and user-profile field support |
| JSON-LD schema (WP-05) | Implemented with PHPUnit coverage and conservative Yoast/Rank Math coexistence |
| AI consent (WP-06) | Initial slice implemented; feed-level rights metadata and audit logging remain |
| CI/CD | Present and passing on supported PHP/WP matrix combinations |
| Documentation | Strong project/governance docs; consumer output reference exists and now covers feeds plus HTML-head outputs |

## Live adapter verification note

Live verification on `single-site-local.local` now confirms that the same two-author post emits the same multi-author Byline shape in RSS2 and Atom under both PublishPress Authors and Co-Authors Plus.

The same local verification run also confirmed the linked-user URL parity fix:

- RSS2 and Atom matched on repeated `byline:author` / `byline:role` pairs
- JSON Feed stayed structurally consistent across both adapters
- Co-Authors Plus now falls back to linked-user `user_url` when the CAP `website` field is empty

That means the earlier CAP/PPA discrepancy for linked-user `url` normalization is resolved for the tested local case. The remaining adapter work is now about future plugin support and upstream drift, not this specific CAP parity bug.

## Key risks

1. **Post-Gate-A scope drift.** The vision is broader than the active roadmap. Without discipline, WP-05/06 can turn into premature identity-framework work instead of focused output features.
2. **Upstream plugin drift.** CAP, PPA, and HM Authorship now have dedicated CI coverage against installed upstream plugins, but future adapter tranches such as Molongui will need the same level of real-plugin validation.
3. **Unsupported-plugin behavior.** Live verification showed that sites using unsupported multi-author plugins can still have a mismatch between core author strings and Byline output. That remains expected today and argues for explicit backlog tracking of later adapter work.
4. **WP-06 complexity.** Rights and consent remain the most stateful and policy-sensitive part of the roadmap.
5. **Pre-1.0 spec divergence.** Multi-author item structure, JSON Feed structure, and terminology drift remain unresolved upstream issues.

## Recommendations

1. **Treat HM Authorship as shipped and verified.** Keep its integration tests in the normal CI path so upstream drift is caught early.
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
