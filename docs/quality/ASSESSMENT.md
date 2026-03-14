# Project Assessment — Byline Feed Plugin

## Executive summary

The Byline Feed plugin addresses a real interoperability gap: WordPress sites with multiple authors often publish feeds that lose structured attribution. The current plugin now has the MVP foundation in place: adapter normalization, RSS2/Atom Byline output, perspective support, PHPUnit coverage, PHPCS enforcement, and GitHub Actions CI. What remains is no longer “make the MVP real,” but “decide which post-MVP output channel to ship next and harden integrations further.”

## Scope and key components

- **Adapter layer:** Detects Co-Authors Plus, PublishPress Authors, or core WordPress and normalizes author data into a common contract.
- **Feed output:** RSS2 and Atom feeds enriched with `xmlns:byline`, feed-level contributor registries, item-level author refs, roles, and perspective.
- **Perspective meta field:** Per-post editorial intent with block editor support and feed output.
- **fediverse:creator output (planned):** HTML meta tags for Mastodon author attribution.
- **JSON-LD schema output (planned):** Multi-author Article + Person structured data.
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
    → HTML head: fediverse:creator meta tags (WP-04, planned)
    → HTML head / JSON-LD: Article + Person graph (WP-05, planned)
    → HTTP headers / meta / files: rights and consent signals (WP-06, planned)
```

## Current state

| Area | Status |
| --- | --- |
| Adapter layer (WP-01) | Implemented, tested, and CI-verified |
| Feed output (WP-02) | Implemented for RSS2, Atom, and JSON Feed with automated coverage |
| Perspective field (WP-03) | Implemented, built locally, covered in feed tests, and manually verified |
| fediverse:creator (WP-04) | Not started |
| JSON-LD schema (WP-05) | Not started |
| AI consent (WP-06) | Not started |
| CI/CD | Present and passing on supported PHP/WP matrix combinations |
| Documentation | Strong project/governance docs; consumer output reference exists and now covers current feed formats |

## Key risks

1. **Upstream plugin drift.** CAP and PPA have PHPUnit coverage and local manual verification, but not yet dedicated CI jobs against installed upstream plugins.
2. **Unsupported-plugin behavior.** Live verification showed that sites using unsupported multi-author plugins can still have a mismatch between core author strings and Byline output. That is expected today, but it argues for explicit backlog tracking if HM Authorship support matters.
3. **Scope expansion pressure.** The documentation landscape still covers broader ideas beyond the plugin’s immediate roadmap. Without discipline, WP-04/05/06 can sprawl.
4. **WP-06 complexity.** Rights and consent remain the most stateful and policy-sensitive part of the roadmap.

## Recommendations

1. **Choose the next output channel intentionally.** WP-04 and WP-05 are the most natural next feature tranches; WP-06 should not be rushed.
2. **Add real-plugin CI validation.** Installing CAP and PPA in dedicated CI jobs is the most valuable remaining hardening step for the current adapter architecture.
3. **Add editor-level verification.** Browser or end-to-end checks for the perspective UI would close the last major MVP-era verification gap.
4. **Expand tests and docs in lockstep.** JSON Feed now exists in code, so test coverage and consumer docs should stay aligned as output expands.
5. **Keep using the new governance files.** `CHANGELOG.md`, `RELEASE_NOTES.md`, templates, and contributor guidance only matter if they become part of normal release practice.

## Related documents

- [Implementation Strategy/implementation-spec.md](../../Implementation%20Strategy/implementation-spec.md) — Full spec, work packages, and cross-cutting concerns
- [Implementation Strategy/gap-analysis.md](../../Implementation%20Strategy/gap-analysis.md) — Current remaining gaps
- [TEST_COVERAGE_MATRIX.md](./TEST_COVERAGE_MATRIX.md) — Test coverage status by domain
- [TDD_TESTING_STANDARD.md](./TDD_TESTING_STANDARD.md) — Testing protocol
- [author-identity-vision.md](../vision/author-identity-vision.md) — Vision document
