# Test Coverage Matrix

## How to read

- **Covered:** Reliable automated checks/tests exist, are committed to the repo, and are expected to run in CI.
- **Partial:** Some tests exist, but edge cases or contracts are missing.
- **Gap:** No meaningful automated coverage yet.
- **Blocked:** Tests cannot run due to missing infrastructure.

## Infrastructure status

| Area | Status | Notes |
| --- | --- | --- |
| PHPUnit configuration | **Covered** | `phpunit.xml.dist` exists with `failOnRisky` and `failOnWarning` enabled. |
| WordPress test harness | **Covered** | `bin/install-wp-tests.sh` and `tests/phpunit/bootstrap.php` created. |
| CI pipeline | **Covered** | `.github/workflows/ci.yml` — PHPUnit matrix (PHP 7.4–8.3 × WP 6.0–latest), PHPCS, Node build. |
| Node build | **Covered** | CI job runs `npm run build` and the local build command is defined. |
| PHPCS automation | **Covered** | `composer lint` configured with correct excludes. CI job runs PHPCS with `cs2pr` formatter. |
| Composer test script | **Covered** | `composer test` points to `phpunit --configuration=phpunit.xml.dist`. |
| Browser E2E harness | **Covered** | `playwright.config.js`, `.wp-env.json`, and `tests/e2e/perspective-panel.spec.js` provide a self-contained editor test path. |

## Core domains — MVP (WP-01/02/03)

| Domain | Status | Test file | Notes |
| --- | --- | --- | --- |
| Core adapter — single author resolution | **Covered** | `test-adapter-core.php` | Happy path, role mapping, zero-value fields. |
| Core adapter — invalid/missing author | **Covered** | `test-adapter-core.php` | Returns empty array for user ID 0. |
| Core adapter — role mapping | **Covered** | `test-adapter-core.php` | Editor → staff, Author → contributor. |
| CAP adapter — mixed user/guest authors | **Covered** | `test-adapter-cap.php` | Normalization covered for both WP user and guest author objects. |
| CAP adapter — guest author detection | **Covered** | `test-adapter-cap.php` | Guest objects map to `role=guest`, `is_guest=true`, and `user_id=0`. |
| CAP adapter — author ordering | **Partial** | `test-adapter-cap.php` | Normalization covered; `get_coauthors()` ordering path still needs a function-level integration test. |
| PPA adapter — term meta resolution | **Covered** | `test-adapter-ppa.php` | Term meta description/avatar mapping verified. |
| PPA adapter — linked user fallback | **Covered** | `test-adapter-ppa.php` | Fallback to linked user profile when term meta is missing is verified. |
| PPA adapter — guest author handling | **Covered** | `test-adapter-ppa.php` | Guest object mapping (`role=guest`, no user-linked fields) is verified. |
| Adapter contract validation | **Covered** | `test-author-contract.php` | Invalid entries are dropped and optional fields are normalized before output layers consume author data. |
| RSS2 namespace declaration | **Covered** | `test-feed-rss2.php` | Verifies `xmlns:byline` present. |
| RSS2 contributors block | **Covered** | `test-feed-rss2.php` | Verifies `<byline:person>` in channel head. |
| RSS2 per-item author refs | **Covered** | `test-feed-rss2.php` | Verifies `<byline:author ref>` matches contributor. |
| RSS2 perspective output | **Covered** | `test-feed-rss2.php` | Present when set, absent when unset. |
| RSS2 well-formed XML | **Covered** | `test-feed-rss2.php` | XML parse succeeds. |
| RSS2 profile/now/uses elements | **Covered** | `test-feed-rss2.php` | Verifies `byline:profile`, `byline:now`, and `byline:uses` output when normalized fields are present. |
| RSS2 multi-author per item | **Covered** | `test-feed-rss2.php` | Verifies multiple `<byline:author>` refs are emitted when multiple normalized authors are present. |
| RSS2 standard elements preserved | **Covered** | `test-feed-rss2.php` | Template-level render verifies core `dc:creator` survives alongside Byline output. |
| RSS2 empty-field omission | **Covered** | `test-feed-rss2.php` | Verifies empty optional person fields do not emit context/url/avatar elements. |
| Atom namespace declaration | **Covered** | `test-feed-atom.php` | Verifies `xmlns:byline` present. |
| Atom contributors block | **Covered** | `test-feed-atom.php` | Verifies `<byline:person>` output in feed head. |
| Atom per-entry author refs | **Covered** | `test-feed-atom.php` | Verifies `<byline:author ref>` output for entries, including multi-author cases. |
| Atom profile/now/uses elements | **Covered** | `test-feed-atom.php` | Verifies `byline:profile`, `byline:now`, and `byline:uses` output when normalized fields are present. |
| Atom filter parity with RSS2 | **Covered** | `test-feed-atom.php` | Atom contributors and entry output now honor the same person/item XML filters as RSS2. |
| JSON Feed 1.1 fallback document | **Covered** | `test-feed-json.php` | Verifies standalone renderer returns valid JSON Feed 1.1 with feed-level `_byline.org` metadata. |
| JSON Feed feed-level author deduplication | **Covered** | `test-feed-json.php` | Verifies top-level `authors` are deduplicated across posts and include `_byline.id`. |
| JSON Feed per-item author roles | **Covered** | `test-feed-json.php` | Verifies item `authors[]._byline.role` values survive multi-author output. |
| JSON Feed perspective output | **Covered** | `test-feed-json.php` | Verifies `_byline.perspective` is present when set and absent when unset. |
| JSON Feed empty-field omission | **Covered** | `test-feed-json.php` | Verifies empty optional author fields are omitted from standard and `_byline` payloads. |
| Perspective — valid value accepted | **Covered** | `test-perspective.php` | All 12 allowed values pass. |
| Perspective — invalid value rejected | **Covered** | `test-perspective.php` | Returns empty string. |
| Perspective — filter override | **Covered** | `test-perspective.php` | Filter can replace value. |
| Perspective — empty when unset | **Covered** | `test-perspective.php` | No meta returns empty. |
| Perspective — block editor panel | **Covered** | `tests/e2e/perspective-panel.spec.js` | Playwright + `wp-env` verifies panel render, selection, save, and persistence after reload. |

## Core domains — Post-MVP (WP-04/05/06)

| Domain | Status | Test file | Notes |
| --- | --- | --- | --- |
| fediverse:creator meta tag output | **Covered** | `test-fediverse.php` | Singular output, multi-author output, filter overrides, and non-singular omission are covered. |
| fediverse handle normalization | **Covered** | `test-fediverse.php`, `test-author-meta.php` | Handles normalize to leading `@` on save and before output. |
| fediverse user profile field | **Covered** | `test-author-meta.php` | Render, save, normalization, delete, and meta registration are covered. |
| ActivityPub actor URL resolution for WP-04/05 | **Partial** | `test-author-meta.php` | Empty fallback and filter override are covered. Positive integration against the real ActivityPub plugin is still missing. |
| JSON-LD Article + Person schema | **Covered** | `test-schema.php` | Singular Article output, ordered multi-author `Person` arrays, publisher organization, and guest-author omission behavior are covered. |
| JSON-LD sameAs from profiles | **Covered** | `test-schema.php` | `sameAs` is populated from normalized `profiles[]` entries. |
| JSON-LD sameAs extension with `ap_actor_url` | **Covered** | `test-schema.php` | `ap_actor_url` is added only when present and never inferred from other profile URLs. `did:web:` remains intentionally outside the active matrix. |
| JSON-LD Yoast/Rank Math detection | **Covered** | `test-schema.php` | Known SEO-plugin conflict detection disables output by default and respects override filters. |
| AI consent resolution logic | **Gap** | Missing file | `inc/rights.php` and tests do not exist. |
| AI consent HTML meta output | **Gap** | Missing file | No code exists. |
| AI consent TDM headers | **Gap** | Missing file | No code exists. |
| ai.txt generation | **Gap** | Missing file | No code exists. |
| Consent audit logging | **Gap** | Missing file | No code exists. |

## Priority backlog (highest impact first)

1. **Add HM Authorship adapter tests when that tranche starts.** Unit normalization + real-plugin integration coverage should land together.
2. **Add `test-rights.php` when WP-06 starts.** Treat rights/consent coverage as part of the work package from day one, after the HM Authorship tranche.
3. **Add real ActivityPub-plugin integration checks for `ap_actor_url`.** The current WP-04/WP-05 suite intentionally keeps actor resolution conservative and only partially covered.
4. **Add browser coverage for the fediverse profile field.** The save/normalization logic is covered in PHPUnit, but the user-profile UI is not yet covered in a browser run.
5. **Add browser coverage for the classic editor perspective metabox.** Lower priority than the block editor path, but still useful as fallback hardening.
6. **Optional later hardening:** add deeper Byline spec-conformance and round-trip parsing tests for feed output.

## Quality target

- No **Blocked** items in infrastructure.
- No **Gap** items in security-critical or adapter domains before Gate A.
- CI green on PHPCS + PHPUnit matrix for all PRs.
- Every spec divergence fix includes a test proving the corrected behavior.

## Related documents

- [ASSESSMENT.md](ASSESSMENT.md) — Project assessment
- [Implementation Strategy/gap-analysis.md](../../Implementation%20Strategy/gap-analysis.md) — Detailed gap audit
- [TDD_TESTING_STANDARD.md](TDD_TESTING_STANDARD.md) — Testing protocol
- [Implementation Strategy/implementation-spec.md](../../Implementation%20Strategy/implementation-spec.md) — Test strategy and testing matrix
