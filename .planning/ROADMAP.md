# Roadmap: Author Identity

## Overview

This roadmap picks up from a brownfield RC state rather than a blank slate (`v0.1.0-rc5` as of 2026-10-14). Its target is the stable Byline Feed `0.1.0` release. Cross-repo context and release order live in [SUITE.md](SUITE.md). The near-term path is to harden verification, turn known pre-1.0 spec questions into explicit decisions, reconcile release artifacts with current `main`, and then make an intentional next-cut decision for the Byline Feed plugin.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Verification Hardening** - Close the remaining automated coverage gaps and stabilize release verification.
- [x] **Phase 2: Spec Alignment Decisions** - Convert known Byline-spec and terminology drift into explicit, test-backed release decisions.
- [x] **Phase 3: Release Surface Reconciliation** - Make the packaged artifact, release docs, and smoke-test evidence match current `main`.
- [x] **Phase 4: RC Closure and Next Cut** - Triage feedback, resolve blockers, and decide the next RC or stable release move.

## Phase Details

### Phase 1: Verification Hardening
**Goal:** Make plugin verification reproducible enough that release readiness can be judged from current evidence instead of assumption.
**Depends on:** Nothing (first phase)
**Requirements:** [VER-01, VER-02, VER-03, VER-04]
**Success Criteria** (what must be TRUE):
  1. Maintainer can run a dependable single-site verification path before cutting a release.
  2. Multisite/integration verification is either runnable end-to-end or clearly documented with exact blockers.
  3. Remaining author-ordering, empty-field, and special-character gaps are covered by automated tests.
  4. CI coverage and trigger paths are explicit for the code that can affect shipped plugin behavior.
**Plans:** 2 plans

Plans:
- [x] 01-01: Close the remaining PHPUnit and E2E coverage gaps called out in the quality docs.
- [x] 01-02: Stabilize local verification entrypoints and CI trigger coverage for release-significant changes.

### Phase 2: Spec Alignment Decisions
**Goal:** Decide what must change before stable 1.0 and land the chosen output, terminology, and documentation updates coherently.
**Depends on:** Phase 1
**Requirements:** [SPEC-01, SPEC-02, SPEC-03]
**Success Criteria** (what must be TRUE):
  1. A reviewed list exists of Byline-spec divergences, with each one marked as block, defer, or accept for the next release.
  2. Terminology is consistent across plugin docs, release docs, and user-facing descriptions.
  3. Any adopted pre-1.0 output changes are backed by tests and consumer-facing documentation.
**Plans:** 2 plans

Plans:
- [x] 02-01: Turn the current spec/gap analysis into explicit stable-release decisions.
- [x] 02-02: Apply approved terminology and output updates across code, tests, and docs.

### Phase 3: Release Surface Reconciliation
**Goal:** Ensure the release artifact and release documentation describe the same thing as the code currently on `main`.
**Depends on:** Phase 2
**Requirements:** [REL-01, REL-02]
**Success Criteria** (what must be TRUE):
  1. A release zip can be built from current `main` and tied to a specific version decision.
  2. Changelog, release notes, README references, and checklist evidence align with the artifact being shipped.
  3. Smoke-test evidence is current rather than inherited from an older RC cut.
**Plans:** 2 plans

Plans:
- [x] 03-01: Refresh packaging, installation, and smoke-test verification for the next cut.
- [x] 03-02: Reconcile changelog, release notes, README, and checklist content with the intended artifact.

### Phase 4: RC Closure and Next Cut
**Goal:** Convert release-candidate feedback and current branch state into an explicit next action: new RC, continued RC stabilization, or stable release prep.
**Depends on:** Phase 3
**Requirements:** [REL-03, REL-04]
**Success Criteria** (what must be TRUE):
  1. Feedback from the active RC issue is triaged into concrete ship, block, or defer decisions.
  2. The changes after `v0.1.0-rc3` (rc4 and rc5) are explicitly accounted for in the release decision.
  3. The project has a documented next-cut decision with supporting verification evidence.
**Plans:** 2 plans

Plans:
- [x] 04-01: Triage RC feedback, open blockers, and known release questions.
- [x] 04-02: Prepare and document the next-cut decision package.

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Verification Hardening | 2/2 | Complete | 2026-10-21 |
| 2. Spec Alignment Decisions | 2/2 | Complete | 2026-10-21 |
| 3. Release Surface Reconciliation | 2/2 | Complete | 2026-10-21 |
| 4. RC Closure and Next Cut | 2/2 | Complete: next cut is 0.1.0 stable (PR #73) | 2026-10-21 |
