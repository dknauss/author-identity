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
| RSS2 profile/now/uses elements | **Gap** | `test-feed-rss2.php` | Elements not implemented in `output_person()`. |
| RSS2 multi-author per item | **Covered** | `test-feed-rss2.php` | Verifies multiple `<byline:author>` refs are emitted when multiple normalized authors are present. |
| RSS2 standard elements preserved | **Covered** | `test-feed-rss2.php` | Template-level render verifies core `dc:creator` survives alongside Byline output. |
| RSS2 empty-field omission | **Covered** | `test-feed-rss2.php` | Verifies empty optional person fields do not emit context/url/avatar elements. |
| Atom namespace declaration | **Covered** | `test-feed-atom.php` | Verifies `xmlns:byline` present. |
| Atom contributors block | **Covered** | `test-feed-atom.php` | Verifies `<byline:person>` output in feed head. |
| Atom per-entry author refs | **Covered** | `test-feed-atom.php` | Verifies `<byline:author ref>` output for entries, including multi-author cases. |
| Atom filter parity with RSS2 | **Covered** | `test-feed-atom.php` | Atom contributors and entry output now honor the same person/item XML filters as RSS2. |
| Perspective — valid value accepted | **Covered** | `test-perspective.php` | All 12 allowed values pass. |
| Perspective — invalid value rejected | **Covered** | `test-perspective.php` | Returns empty string. |
| Perspective — filter override | **Covered** | `test-perspective.php` | Filter can replace value. |
| Perspective — empty when unset | **Covered** | `test-perspective.php` | No meta returns empty. |
| Perspective — block editor panel | **Gap** | No test | TSX never built; no browser or integration test. |

## Core domains — Post-MVP (WP-04/05/06)

| Domain | Status | Test file | Notes |
| --- | --- | --- | --- |
| fediverse:creator meta tag output | **Gap** | Missing file | `inc/fediverse.php` and tests do not exist. |
| fediverse handle normalization | **Gap** | Missing file | No code exists. |
| fediverse user profile field | **Gap** | Missing file | No UI exists. |
| JSON-LD Article + Person schema | **Gap** | Missing file | `inc/schema.php` and tests do not exist. |
| JSON-LD sameAs from profiles | **Gap** | Missing file | No code exists. |
| JSON-LD Yoast/Rank Math detection | **Gap** | Missing file | No code exists. |
| AI consent resolution logic | **Gap** | Missing file | `inc/rights.php` and tests do not exist. |
| AI consent HTML meta output | **Gap** | Missing file | No code exists. |
| AI consent TDM headers | **Gap** | Missing file | No code exists. |
| ai.txt generation | **Gap** | Missing file | No code exists. |
| Consent audit logging | **Gap** | Missing file | No code exists. |

## Priority backlog (highest impact first)

1. ~~**Create `phpunit.xml.dist` and test bootstrap.**~~ Done — `phpunit.xml.dist`, `tests/phpunit/bootstrap.php`, `bin/install-wp-tests.sh`.
2. ~~**Create `.github/workflows/ci.yml`.**~~ Done — PHPUnit matrix, PHPCS, Node build jobs.
3. ~~**Write `test-adapter-cap.php`.**~~ Done — normalization coverage added for user+guest CAP objects.
4. ~~**Write `test-adapter-ppa.php`.**~~ Done — normalization coverage added for term-meta, user-fallback, and guest paths.
5. ~~**Write `test-feed-atom.php`.**~~ Done — Atom namespace, contributors, entry refs, perspective, omission, and XML coverage added.
6. ~~**Add an RSS2 template-level preservation test for standard feed elements.**~~ Done — full template render now verifies `dc:creator` survives alongside Byline output.
7. ~~**Add adapter contract validation tests.**~~ Done — malformed entries are now rejected before feed rendering.
8. ~~**Run `npm run build` and verify perspective panel loads.**~~ CI job added and local build command is part of baseline verification.

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
