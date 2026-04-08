# Phase 1: Verification Hardening - Research

**Researched:** 2026-04-08
**Domain:** WordPress plugin verification hardening, release-significant test coverage, and CI entrypoints
**Confidence:** HIGH

## Summary

Phase 1 is a brownfield hardening phase, not a testing-stack invention phase. The repo already has a strong base: PHPUnit 9.6 + WordPress core test harness, dedicated integration jobs for Co-Authors Plus / PublishPress Authors / HM Authorship / ActivityPub, Playwright E2E with `wp-env`, PHPCS, asset builds, and a passing `main` CI state as of **2026-04-08**. The planning focus should be narrow: make the existing verification paths reproducible and explicit enough that a release decision can rely on current evidence instead of memory.

The one clearly open automated gap is **CAP ordering verification**: `tests/phpunit/test-integration-cap.php` has a test named for order, but it only asserts presence/count, not actual order. The broader “empty-field and special-character” backlog needs one reconciliation pass before planning because repo docs conflict: the code-review plan says those feed/schema cases were closed on **2026-03-20**, while the coverage matrix and gap analysis still list remaining hardening work. Treat that as a documentation-and-scope clarification task, not as proof that the whole area is still open.

The other major planning need is operational: the repo has the building blocks for single-site, multisite, and browser verification, but not yet a fully explicit release-verification contract. Local PHPUnit depends on Docker; `wp-env` also depends on Docker and is currently invoked via `npx` without a direct pinned dependency; multisite is supported by scripts but not documented as a maintainer-facing path; and CI cannot currently be launched manually from GitHub UI because `ci.yml` does not define `workflow_dispatch`.

**Primary recommendation:** Use Phase 1 to close the one definite coverage gap, reconcile any remaining edge-case backlog, and turn the existing scripts/workflow into pinned, documented, manually-runnable verification entrypoints.

## Recommended 2-Plan Split

1. **01-01 — Coverage gap closure and evidence refresh**
   - Add a real order assertion for CAP integration.
   - Reconcile the conflicting docs about empty-author / special-character hardening.
   - Add only the remaining missing tests proven by that reconciliation.

2. **01-02 — Verification entrypoints and CI hardening**
   - Make single-site and multisite verification commands explicit and maintainer-facing.
   - Pin `@wordpress/env` usage for reproducibility.
   - Add manual CI dispatch and document exact release-verification commands/blockers.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| VER-01 | Maintainer can run a reliable single-site automated verification path for shipped plugin behavior before a release cut. | Existing local PHPUnit runner, Playwright harness, and build steps already exist; Phase 1 should consolidate them into an explicit maintained path. |
| VER-02 | Maintainer can run or clearly document a reliable multisite/integration verification path before a release cut. | `bin/run-integration-tests.sh` already supports `WP_MULTISITE=1`; Phase 1 should either prove that path end-to-end or document exact blockers. |
| VER-03 | CI covers the code paths on `main` that can change shipped plugin behavior before the next release cut. | Current CI already covers plugin code well; remaining hardening is around manual dispatch, trigger clarity, and release-significant verification entrypoints. |
| VER-04 | Remaining edge cases for author ordering, empty fields, and special characters are covered by automated tests. | CAP ordering is definitely still open; empty/special-character scope must be reconciled against conflicting repo docs before planning exact test tasks. |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHPUnit | 9.6.34 | PHP/unit/integration test runner | Matches current plugin support floor and existing suite. |
| Yoast PHPUnit Polyfills | 2.0.5 | WordPress/PHP compatibility helpers for tests | Standard bridge for WP plugin test suites. |
| WordPress core test suite | repo-managed via `bin/install-wp-tests.sh` | Boots WP integration tests against real core | Existing project standard; already wired into CI and local scripts. |
| Playwright | 1.59.1 | Browser E2E verification | Already committed and used for editor/feed smoke coverage. |
| `@wordpress/scripts` | 31.8.0 | Build editor assets | Existing plugin build path and CI contract. |
| GitHub Actions | current repo workflow | CI orchestration | Already runs PHPUnit matrix, plugin integrations, PHPCS, build, and E2E. |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `@wordpress/env` | **currently unpinned direct dependency** | Docker-backed WordPress environment for E2E | Use for browser verification, but Phase 1 should pin it directly or via explicit versioned `npx`. |
| MySQL | 8.0 in CI / 8.4 default in local runner | Database backing for integration tests | Use via existing scripts; don’t invent alternate DB setup unless the current path is proven broken. |
| WPCS | 3.3.0 | Coding standards enforcement | Keep in fast verification path for release-significant changes. |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Existing WP core harness + local scripts | Custom Docker Compose or bespoke test bootstrap | Worse: duplicates working infrastructure and increases maintenance. |
| `wp-env` + Playwright | Custom browser/dev server orchestration | Worse: `wp-env` is already the project’s chosen WordPress browser harness. |
| Existing matrix + dedicated integration jobs | One giant omnibus CI job | Worse: loses failure isolation across adapters/plugins. |

**Installation:**
```bash
cd byline-feed
composer install
npm install
```

## Architecture Patterns

### Recommended Project Structure
```text
byline-feed/
├── bin/                 # Maintainer-facing verification/build entrypoints
├── inc/                 # Plugin runtime code
├── tests/phpunit/       # Unit + integration coverage
├── tests/e2e/           # Browser verification
├── phpunit.xml.dist     # PHP test config
├── playwright.config.js # Browser test config
└── .wp-env.json         # Local WordPress E2E environment
```

### Pattern 1: Scripted local PHPUnit wrapper around the WP test harness
**What:** Keep local PHPUnit running through the existing wrapper scripts rather than ad hoc env setup.
**When to use:** Any single-site or multisite PHP verification before release.
**Example:**
```bash
bash byline-feed/bin/run-phpunit-local.sh
# inferred multisite variant from current script contract:
WP_MULTISITE=1 bash byline-feed/bin/run-phpunit-local.sh
```
**Source:** repo scripts `byline-feed/bin/run-phpunit-local.sh`, `byline-feed/bin/run-integration-tests.sh`

### Pattern 2: Separate environment startup from browser test execution
**What:** Keep `wp-env start` / Docker checks separate from Playwright test execution.
**When to use:** CI and local E2E runs.
**Example:**
```yaml
- name: Verify Docker is available
  run: docker info > /dev/null 2>&1 || { echo "Docker is not running"; exit 1; }
- name: Start WordPress environment
  run: npx @wordpress/env start
- name: Configure WordPress for E2E
  run: bash bin/setup-e2e.sh --skip-start
- name: Run Playwright tests
  run: npx playwright test
```
**Source:** `.github/workflows/ci.yml`, commit `739d17f` on 2026-04-08

### Pattern 3: Dedicated integration jobs per upstream plugin
**What:** Keep CAP, PPA, HM Authorship, and ActivityPub verification isolated in separate CI jobs.
**When to use:** Any upstream-plugin drift risk.
**Example:** separate `integration-cap`, `integration-ppa`, `integration-authorship`, and `integration-activitypub` jobs in `ci.yml`.

### Anti-Patterns to Avoid
- **Rebuilding the test stack:** Don’t replace the current WP harness / Playwright setup; harden it.
- **Opaque one-shot verification scripts:** The repo already learned this lesson in commit `739d17f`; keep setup and execution separable.
- **Assuming a test name equals real coverage:** The CAP order test currently overstates what it proves.
- **Relying on transitive `npx` behavior for reproducibility:** Pin `@wordpress/env` directly or version the `npx` invocation.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| WordPress integration bootstrap | Custom PHPUnit bootstrap + custom core download flow | Existing `bin/install-wp-tests.sh` + `phpunit.xml.dist` | Already repo-standard and CI-proven. |
| Disposable WP browser env | Custom Docker compose + custom WP provisioning | `@wordpress/env` + `bin/setup-e2e.sh` | Already chosen project pattern with fixtures and login assumptions wired in. |
| Multisite DB/config wiring | Ad hoc config edits | Existing `bin/run-integration-tests.sh` multisite branch | Script already rewrites config safely for single-site vs multisite. |
| CI rerun plumbing | Manual branch-poke commits | `workflow_dispatch` on the existing workflow | Standard GitHub manual-run pattern. |

**Key insight:** Phase 1 should standardize existing building blocks, not introduce parallel infrastructure.

## Common Pitfalls

### Pitfall 1: Unpinned `wp-env` execution
**What goes wrong:** `package.json` uses `npx @wordpress/env` behavior without a direct pinned dependency, so local/CI E2E setup can drift over time.
**Why it happens:** `@wordpress/env` is not directly locked in `byline-feed/package-lock.json`.
**How to avoid:** Add `@wordpress/env` as a direct dev dependency or version the `npx` invocation explicitly.
**Warning signs:** Different maintainers see different `wp-env` behavior without repo changes.

### Pitfall 2: CAP ordering gap hidden by a misleading test name
**What goes wrong:** The current integration test says it checks order, but only asserts presence/count.
**Why it happens:** The test never compares the returned author ID sequence to the upstream sequence.
**How to avoid:** Assert exact ordered ID arrays, not only `contains` and `count`.
**Warning signs:** A future regression could reorder authors while still keeping the test green.

### Pitfall 3: Multisite path exists but lacks current evidence
**What goes wrong:** Scripts support multisite, but maintainers still cannot confidently use that path at release time.
**Why it happens:** The repo has capability without explicit operator-facing documentation or recent evidence.
**How to avoid:** Either prove `WP_MULTISITE=1` locally/CI and document it, or document the exact blocker verbatim.
**Warning signs:** Release notes say “multisite supported” but no dated run evidence exists.

### Pitfall 4: CI is strong but not manually dispatchable
**What goes wrong:** The workflow cannot be launched from GitHub UI/CLI for a release-verification rerun unless code changes or someone reruns an existing run.
**Why it happens:** `ci.yml` lacks `workflow_dispatch`.
**How to avoid:** Add manual dispatch while keeping existing `push`/`pull_request` triggers.
**Warning signs:** A maintainer wants a fresh release-cut run on `main` and has no explicit entrypoint.

### Pitfall 5: Planning against stale backlog text
**What goes wrong:** The planner over-allocates work to already-closed empty/special-character cases.
**Why it happens:** `implementation-strategy/code-review-plan.md` and the quality docs are not fully reconciled.
**How to avoid:** Start Plan 01-01 with a short evidence reconciliation pass.
**Warning signs:** Docs disagree on whether a gap is open.

## Code Examples

Verified patterns from current repo and official docs:

### Single-site PHP verification
```bash
cd byline-feed
bash bin/run-phpunit-local.sh
```
**Source:** `README.md`, `byline-feed/bin/run-phpunit-local.sh`

### Browser verification with explicit setup
```bash
cd byline-feed
npm run build
npx @wordpress/env start
bash bin/setup-e2e.sh --skip-start
npx playwright test
```
**Source:** repo scripts + `.github/workflows/ci.yml`

### Manual CI trigger pattern to add
```yaml
on:
  workflow_dispatch:
  push:
    branches: [main]
    paths:
      - 'byline-feed/**'
      - '.github/workflows/ci.yml'
```
**Source:** GitHub Actions docs on `workflow_dispatch` and current repo workflow

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Opaque E2E setup inside one script call | Explicit Docker preflight + separate `wp-env start` + `--skip-start` setup phase | 2026-04-08 (`739d17f`) | Better failure visibility and more reproducible CI debugging. |
| Implicit confidence from old green runs | Dated, rerunnable verification evidence | This phase | Makes release readiness evidence-based instead of assumption-based. |
| Ad hoc `npx` package resolution | Direct-pinned local tool dependency or versioned `npx` call | Recommended for this phase | Reduces toolchain drift. |

**Deprecated/outdated:**
- Treating the current CAP integration test as sufficient for ordering — it is not.
- Treating local E2E as reproducible without pinning the `wp-env` toolchain — too much drift risk.

## Open Questions

1. **Which empty-field / special-character gaps are still actually open?**
   - What we know: feed/schema unit coverage for these cases already exists in current test files.
   - What's unclear: whether the remaining backlog refers only to rights/meta/enrichment paths, or whether the docs are stale.
   - Recommendation: make reconciliation the first task in Plan 01-01.

2. **Does the current multisite local path pass end-to-end on a maintainer machine?**
   - What we know: `bin/run-integration-tests.sh` supports `WP_MULTISITE=1`.
   - What's unclear: current dated evidence, because Docker-backed verification was unavailable in this session.
   - Recommendation: either prove it in Phase 1 or document the blocker exactly.

3. **Should CI path filters stay narrow or also add a manual trigger?**
   - What we know: current automatic triggers are limited to `byline-feed/**` and the workflow file itself.
   - What's unclear: whether maintainers want fresh release-cut runs from GitHub UI/CLI without new commits.
   - Recommendation: add `workflow_dispatch`; keep path filters narrow unless a concrete missed-behavior case is found.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 9.6.34 + WordPress core test harness; Playwright 1.59.1 |
| Config file | `byline-feed/phpunit.xml.dist`, `byline-feed/playwright.config.js`, `.github/workflows/ci.yml` |
| Quick run command | `composer --working-dir=byline-feed test -- --filter Test_Integration_CAP` |
| Full suite command | `bash byline-feed/bin/run-phpunit-local.sh && npm --prefix byline-feed run test:e2e` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| VER-01 | Release-cut single-site verification path is explicit and dependable | integration + e2e + build smoke | `bash byline-feed/bin/run-phpunit-local.sh && npm --prefix byline-feed run test:e2e && npm --prefix byline-feed run build` | ⚠️ Existing pieces; Phase 1 must consolidate/pin |
| VER-02 | Multisite/integration path is runnable or blocker-documented | integration / manual smoke | `WP_MULTISITE=1 bash byline-feed/bin/run-phpunit-local.sh` | ❌ Wave 0 evidence/doc gap |
| VER-03 | CI can verify release-significant plugin behavior on demand | CI workflow | `gh workflow run ci.yml` *(after adding `workflow_dispatch`)* | ❌ Wave 0 |
| VER-04 | Ordering, empty-field, and special-character gaps are truly closed | unit + integration | `composer --working-dir=byline-feed test -- --filter 'Test_Integration_CAP|Test_Feed_RSS2|Test_Feed_Atom|Test_Feed_JSON|Test_Schema'` | ⚠️ Partial; CAP order definitely open |

### Sampling Rate
- **Per task commit:** `composer --working-dir=byline-feed lint` plus the most relevant targeted PHPUnit test.
- **Per wave merge:** `bash byline-feed/bin/run-phpunit-local.sh`.
- **Phase gate:** Full PHP path + Playwright path + green CI on `main` before `/gsd:verify-work`.

### Wave 0 Gaps
- [ ] Add a maintainer-facing multisite verification entrypoint or doc with exact blocker text.
- [ ] Add `workflow_dispatch` to `.github/workflows/ci.yml` for manual release verification.
- [ ] Pin `@wordpress/env` directly in `byline-feed/package.json` or use an explicit versioned `npx` invocation.
- [ ] Update `tests/phpunit/test-integration-cap.php` to assert exact author order.
- [ ] Reconcile `docs/quality/TEST_COVERAGE_MATRIX.md`, `implementation-strategy/gap-analysis.md`, and `implementation-strategy/code-review-plan.md` so the planner is not working from stale coverage assumptions.

## Sources

### Primary (HIGH confidence)
- Repo docs and configs:
  - `.planning/ROADMAP.md`
  - `.planning/REQUIREMENTS.md`
  - `.planning/STATE.md`
  - `docs/quality/TEST_COVERAGE_MATRIX.md`
  - `docs/quality/ASSESSMENT.md`
  - `docs/quality/RELEASE_CHECKLIST.md`
  - `implementation-strategy/gap-analysis.md`
  - `implementation-strategy/code-review-plan.md`
  - `README.md`
  - `.github/workflows/ci.yml`
  - `byline-feed/bin/run-phpunit-local.sh`
  - `byline-feed/bin/run-integration-tests.sh`
  - `byline-feed/bin/setup-e2e.sh`
  - `byline-feed/tests/phpunit/test-integration-cap.php`
- GitHub Actions workflow syntax: https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax
- GitHub manual workflow runs / `workflow_dispatch`: https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow
- WordPress `@wordpress/env` package docs: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/
- WordPress `wp-env` getting started docs: https://developer.wordpress.org/block-editor/getting-started/devenv/get-started-with-wp-env/
- Playwright CI docs: https://playwright.dev/docs/ci
- npm exec / npx docs: https://docs.npmjs.com/cli/v11/commands/npm-exec

### Secondary (MEDIUM confidence)
- Orchestrator-provided repo state: `main` clean at `739d17f`, latest CI on `main` passed on **2026-04-08**, and local Docker was unavailable in this session.

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: **HIGH** — verified from repo lockfiles, scripts, and workflow config.
- Architecture: **HIGH** — derived from current repo structure and recent CI changes.
- Pitfalls: **MEDIUM** — strong repo evidence, but multisite/local Docker paths were not executable in this session.

**Research date:** 2026-04-08
**Valid until:** 2026-05-08
