# Requirements: Author Identity

**Defined:** 2026-03-29
**Core Value:** Structured author identity must survive publication and syndication as portable, additive metadata from one trusted WordPress source of truth.

## v1 Requirements

### Verification

- [ ] **VER-01**: Maintainer can run a reliable single-site automated verification path for shipped plugin behavior before a release cut.
- [ ] **VER-02**: Maintainer can run or clearly document a reliable multisite/integration verification path before a release cut.
- [ ] **VER-03**: CI covers the code paths on `main` that can change shipped plugin behavior before the next release cut.
- [ ] **VER-04**: Remaining edge cases for author ordering, empty fields, and special characters are covered by automated tests.

### Spec Alignment

- [ ] **SPEC-01**: Maintainer has an explicit list of Byline-spec divergences that block stable 1.0 and which release they target.
- [ ] **SPEC-02**: Plugin docs and user-facing terminology are consistent for author, publication, organization, and rights concepts.
- [ ] **SPEC-03**: Any chosen pre-1.0 output or terminology changes land with tests and consumer-facing docs.

### Release Readiness

- [ ] **REL-01**: Maintainer can build a release zip from current `main` and match it to versioned docs, changelog, and release notes.
- [ ] **REL-02**: Release checklist and smoke-test evidence are current for the next RC or stable cut.
- [ ] **REL-03**: Feedback from the active RC issue is triaged into concrete ship, block, or defer decisions.
- [ ] **REL-04**: Current `main` has a documented next-cut decision that accounts for unreleased post-`rc3` changes and latest CI evidence.

## v2 Requirements

### Adapters

- **ADPT-01**: Plugin supports Molongui with the same normalization and verification bar used for the currently supported adapters.

### Identity Expansion

- **IDEN-01**: The project introduces broader identity-framework work such as `did:web:` only after current output work is complete and a concrete consumer exists.

### Policy Surface

- **AIP-01**: Any expansion beyond the current advisory AI-consent surface is scoped as a separate standards/settings effort.

## Out of Scope

| Feature | Reason |
|---------|--------|
| New CMS targets beyond WordPress | The product strategy is explicitly WordPress-centered |
| Stable 1.0 marketing claims without explicit spec-alignment decisions | Would overstate conformance relative to known pre-1.0 divergences |
| Broad identity-graph or DID work in the current milestone | Not on the active roadmap and would dilute release hardening |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| VER-01 | Phase 1 | Pending |
| VER-02 | Phase 1 | Pending |
| VER-03 | Phase 1 | Pending |
| VER-04 | Phase 1 | Pending |
| SPEC-01 | Phase 2 | Pending |
| SPEC-02 | Phase 2 | Pending |
| SPEC-03 | Phase 2 | Pending |
| REL-01 | Phase 3 | Pending |
| REL-02 | Phase 3 | Pending |
| REL-03 | Phase 4 | Pending |
| REL-04 | Phase 4 | Pending |

**Coverage:**
- v1 requirements: 11 total
- Mapped to phases: 11
- Unmapped: 0 ✓

---
*Requirements defined: 2026-03-29*
*Last updated: 2026-03-29 after initial definition*
