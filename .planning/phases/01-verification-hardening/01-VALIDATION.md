---
phase: 1
slug: verification-hardening
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-08
---

# Phase 1 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit + Playwright + shell-scripted local verification |
| **Config file** | `byline-feed/phpunit.xml.dist`, `byline-feed/playwright.config.js`, `byline-feed/.wp-env.json` |
| **Quick run command** | `composer lint && npm run build` |
| **Full suite command** | `bash bin/run-phpunit-local.sh && npm run test:e2e` |
| **Estimated runtime** | ~5-15 minutes depending on Docker/wp-env startup |

---

## Sampling Rate

- **After every task commit:** Run `composer lint && npm run build`
- **After every plan wave:** Run `bash bin/run-phpunit-local.sh && npm run test:e2e`
- **Before `$gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 15 minutes

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 1-01-01 | 01-01 | 1 | VER-04 | doc / evidence reconciliation | `rg -n "author ordering|empty-field|special-character|hardening" docs/quality/TEST_COVERAGE_MATRIX.md implementation-strategy/gap-analysis.md` | ✅ | ⬜ pending |
| 1-01-02 | 01-01 | 1 | VER-04 | phpunit | `composer test -- --filter test_multi_author_post_returns_all_coauthors_in_order` | ✅ | ⬜ pending |
| 1-01-03 | 01-01 | 1 | VER-04 | phpunit | `composer test` | ✅ | ⬜ pending |
| 1-01-04 | 01-01 | 1 | VER-04 | e2e + docs alignment | `npm run test:e2e && rg -n "CAP adapter — author ordering|special-character|empty-field|hardening" docs/quality/TEST_COVERAGE_MATRIX.md implementation-strategy/gap-analysis.md` | ✅ | ⬜ pending |
| 1-02-01 | 01-02 | 2 | VER-01, VER-02, VER-03 | audit / mapping | `rg -n "run-phpunit-local|run-integration-tests|run-e2e|workflow_dispatch|integration-|phpunit:|playwright|npm run build" byline-feed/bin/run-phpunit-local.sh byline-feed/bin/run-integration-tests.sh byline-feed/bin/run-e2e.sh byline-feed/package.json .github/workflows/ci.yml` | ✅ | ⬜ pending |
| 1-02-02 | 01-02 | 2 | VER-01, VER-02 | fast local verification | `composer lint && npm run build` | ✅ | ⬜ pending |
| 1-02-03 | 01-02 | 2 | VER-01, VER-02 | docs alignment | `rg -n "LOCAL_VERIFICATION|run-phpunit-local|run-integration-tests|test:e2e|verification" docs/quality/RELEASE_CHECKLIST.md docs/quality/LOCAL_VERIFICATION.md` | ✅ | ⬜ pending |
| 1-02-04 | 01-02 | 2 | VER-03 | CI trigger policy | `rg -n "workflow_dispatch|push:|pull_request:|paths:" .github/workflows/ci.yml` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] Confirm a deterministic maintainer command contract for single-site, multisite/integration, and E2E verification.
- [ ] Decide whether a single wrapper script is needed for pre-release local verification.
- [ ] Confirm whether multisite execution is runnable end-to-end or must be recorded as blocked with exact prerequisites.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Local Docker/wp-env prerequisites are available and understandable for maintainers | VER-01, VER-02 | Environment availability cannot be guaranteed from repo files alone | Follow the local verification runbook and confirm missing prerequisites fail loudly with clear messages. |
| CI trigger policy is understandable for release-significant changes | VER-03 | Trigger correctness is partly a workflow/policy review | Review `.github/workflows/ci.yml` paths and compare them to the local runbook + release checklist. |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or documented Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all missing references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15 minutes
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
