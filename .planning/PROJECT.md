# Author Identity

## What This Is

Author Identity is a research-and-implementation repository centered on the Byline Feed WordPress plugin. The project treats WordPress as a durable source of truth for structured authorship metadata, then emits that data across feeds, HTML head output, JSON-LD, fediverse attribution, and AI-consent signaling so author identity can travel with published work.

## Core Value

Structured author identity must survive publication and syndication as portable, additive metadata from one trusted WordPress source of truth.

## Requirements

### Validated

- ✓ Normalize author data from core WordPress, Co-Authors Plus, PublishPress Authors, and HM Authorship — existing
- ✓ Emit additive Byline output in RSS2, Atom, and JSON Feed — existing
- ✓ Store and emit authorial perspective metadata for posts — existing
- ✓ Emit `fediverse:creator` HTML meta tags for supported author profiles — existing
- ✓ Emit multi-author JSON-LD with Yoast and Rank Math coexistence modes — existing
- ✓ Emit the current advisory AI-consent surface (`robots`, `TDMRep`, `ai.txt`, feed rights metadata, consent UI, audit logging) — existing

### Active

- [ ] Close the remaining verification gaps and make pre-release verification more reproducible locally and in CI
- [ ] Decide which Byline-spec and terminology divergences must be resolved before a stable release
- [ ] Align release artifacts, release notes, and smoke-test evidence with current `main`
- [ ] Triage RC feedback and turn it into an explicit next-cut decision

### Out of Scope

- Broader federated identity frameworks such as `did:web:` — deferred until there is a concrete consumer and the current output roadmap is fully shipped
- Non-WordPress CMS support — the project is intentionally centered on WordPress as the author-data source of truth
- Expanding AI-consent signaling into enforcement or policy infrastructure — current scope is advisory metadata only

## Context

The repository contains both project documentation and the `byline-feed` plugin. Gate A is complete, `v0.1.0-rc3` is the active prerelease, and the shipped plugin surface is already broad: adapters, feeds, perspective, fediverse attribution, JSON-LD, and AI-consent signaling are in place. Current work is no longer greenfield feature invention; it is brownfield hardening, pre-1.0 alignment, and disciplined release preparation.

Repository docs already identify the most relevant next work: remaining coverage gaps (especially ordering and edge-case hardening), pre-1.0 Byline spec alignment, and release-process consistency. The current `main` branch is clean locally and slightly ahead of the `v0.1.0-rc3` tag with a PublishPress Authors lint fix plus repo-internal Claude guidance.

## Constraints

- **Compatibility**: WordPress 6.0+ and PHP 7.4+ — plugin headers and tooling target this support floor
- **Architecture**: All output channels must consume the same normalized author contract — this is the core design rule across the repo
- **Scope**: Output is additive and adapter-based — the plugin must not replace standard feed elements or require upstream multi-author plugins as hard dependencies
- **Release discipline**: Any release cut must reconcile source, tests, packaged zip, changelog, and release notes — the repo already has explicit governance docs for this
- **Spec maturity**: Stable 1.0 claims should wait for intentional handling of known Byline-spec divergences — current RC status is acceptable without over-claiming conformance

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Keep Byline output additive rather than replacing core feed metadata | Preserves compatibility and lowers adoption risk | ✓ Good |
| Use a normalized author contract as the single internal source for all outputs | Prevents format-specific drift and duplicated logic | ✓ Good |
| Treat optional multi-author plugins as adapters, not dependencies | Keeps the plugin usable on core WordPress and isolates upstream drift | ✓ Good |
| Keep AI-consent features in an advisory signaling lane | Avoids overpromising enforcement and keeps scope bounded | ✓ Good |
| Defer broader identity frameworks until output channels are solid and consumers are clear | Prevents premature scope expansion beyond the current roadmap | ✓ Good |
| Treat pre-1.0 Byline-spec alignment as a deliberate release decision, not an implicit assumption | Stable-release claims depend on explicit handling of known divergences | — Pending |

---
*Last updated: 2026-03-29 after initial GSD setup*
