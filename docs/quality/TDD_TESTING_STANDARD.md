# TDD + WordPress Testing Standard

## Objective

Adopt a test-driven workflow where every behavior change is introduced through failing tests first, then implemented minimally, then refactored with green tests. All code must be verified against the WordPress test harness across a PHP/WP version matrix before merge.

## Delivery standard

- **Red-Green-Refactor** cycle is required for all behavior changes.
- All pull requests must pass:
  - PHPUnit (WordPress integration suite)
  - PHPCS (WordPress coding standards)
  - Node build (`npm run build` for TypeScript assets)
- New logic must ship with tests at the same time; no deferred tests.
- Bug fixes must include a regression test that fails before the fix.
- Adapter changes must include both unit tests (mocked upstream API) and integration test scenarios (real plugin installed in CI).

## Test pyramid for this plugin

### Unit-style behavior tests (`WP_UnitTestCase`)

- Adapter output shape: normalized author object contract (required fields present, optional fields default correctly).
- Role mapping: WordPress capabilities → Byline role vocabulary.
- Perspective validation: allowed values accepted, invalid values rejected, filter override.
- Consent resolution: most-restrictive-wins logic, post-level override, fallback behavior.

### Integration tests (WordPress boundaries)

- Adapter detection: correct adapter selected based on active plugins.
- Feed generation: post creation → adapter resolution → XML output with correct Byline elements.
- Meta registration: `_byline_perspective`, `byline_feed_fediverse`, `byline_feed_ai_consent` registered and accessible via REST API.
- HTML head output: `fediverse:creator` and `robots` meta tags on singular views, absent on archives.
- HTTP headers: TDM-Rep header on singular views for denied-consent posts.

### Feed validation tests

- XML well-formedness: every generated feed parses without error.
- Byline spec conformance: required attributes and children present, vocabulary values valid, empty fields omitted not empty-element.
- Round-trip integrity: parse generated XML back into author data, verify it matches input.
- Standard element preservation: `<author>`, `<dc:creator>` elements survive Byline additions.

### Tests NOT needed (yet)

- Browser/UI tests for the block editor panel. The TypeScript component is simple enough that compilation + manual QA suffices until the UI grows.
- Performance/load tests. Premature until the plugin is running on production sites.

## Required test cases (minimum by work package)

### WP-01: Adapter layer

| Scenario | Adapter | Input | Expected |
| --- | --- | --- | --- |
| Single author, no plugin | Core | 1 WP user | One normalized object, role from capabilities |
| Missing/invalid author | Core | Post with user ID 0 | Empty array |
| Two co-authors via CAP | CAP | 1 user + 1 guest | Two objects, guest has `is_guest=true`, `role=guest` |
| Three authors via PPA | PPA | 2 users + 1 guest | Three objects, correct order preserved |
| Guest with no data | CAP/PPA | Guest, no bio/avatar/URL | All optional fields default to zero values |
| Malformed adapter output | Any | Object missing `id` field | `_doing_it_wrong` notice in debug, defaults applied |

### WP-02: Feed output

| Scenario | Format | Input | Expected |
| --- | --- | --- | --- |
| Namespace declaration | RSS2 | Any feed | `xmlns:byline` on `<rss>` element |
| Contributors block | RSS2 | 2+ authors in feed | Deduplicated `<byline:person>` elements |
| Per-item author ref | RSS2 | Post with 1 author | `<byline:author ref>` matches contributor `id` |
| Multi-author item | RSS2 | Post with 3 authors | Three `<byline:author>` elements, correct order |
| Perspective present | RSS2 | Post with perspective set | `<byline:perspective>` element with value |
| Perspective absent | RSS2 | Post without perspective | No `<byline:perspective>` element |
| Standard elements preserved | RSS2 | Any post | `<author>` and `<dc:creator>` still present |
| Empty fields omitted | RSS2 | Author with no bio | No `<byline:context>` element (not empty element) |
| Profile links | RSS2 | Author with `profiles` | `<byline:profile>` elements with `href` and `rel` |
| Atom parallel output | Atom | Same scenarios as RSS2 | Equivalent Byline elements in Atom structure |

### WP-03: Perspective

| Scenario | Input | Expected |
| --- | --- | --- |
| Valid value persists | `analysis` in meta | `byline_feed_get_perspective()` returns `analysis` |
| Invalid value rejected | `nonsense` in meta | Returns empty string |
| Filter overrides meta | Filter returns `reporting` | Perspective is `reporting` regardless of meta |
| All 12 values accepted | Each allowed value | All return correctly |
| Empty when unset | No meta set | Returns empty string |

## Quality gates

- CI matrix validates against PHP 7.4, 8.0, 8.1, 8.2, 8.3 and WP 6.0, 6.4, latest.
- PHPCS and PHPUnit must pass before merge.
- Fail build on warnings and risky tests (`failOnRisky`, `failOnWarning` in `phpunit.xml.dist`).
- Keep tests deterministic: no sleep-based timing, no remote network dependencies, no reliance on specific post IDs.

## TDD workflow (per change)

1. Write or extend a failing test demonstrating the required behavior.
2. Implement minimal code to pass the test.
3. Refactor for clarity while keeping tests green.
4. Add edge-case assertions for regression resistance.
5. Submit PR with notes on test-first intent and added coverage.

## Definition of done for any feature PR

- Includes tests proving happy path and failure path.
- CI green across PHP/WP matrix.
- No reduction in existing test coverage for touched modules.
- PHPCS clean.
- If the PR touches feed output: XML well-formedness and Byline spec conformance tests pass.
- If the PR touches an adapter: both unit (mocked) and integration (real plugin, if CI job available) tests pass.

## Related documents

- [TEST_COVERAGE_MATRIX.md](TEST_COVERAGE_MATRIX.md) — Current test coverage status
- [ASSESSMENT.md](ASSESSMENT.md) — Project assessment
- [Implementation Strategy/implementation-spec.md](../../Implementation%20Strategy/implementation-spec.md) — Test strategy and testing matrix
