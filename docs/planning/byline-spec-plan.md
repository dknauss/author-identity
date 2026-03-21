# Byline Spec Assessment and Implementation Plan

## Purpose

This document tracks how the standalone `byline-feed` plugin relates to the Byline spec as it exists today.

It is not the authoritative execution roadmap. Its job is to clarify:

- what the plugin already proves about the spec
- where the plugin currently diverges from the spec
- which spec questions matter before calling the plugin a stable 1.0 implementation
- how HM Authorship fits into later adapter work without redefining the near-term roadmap

For current execution order, use [implementation-spec.md](implementation-spec.md) and [implementation-strategy/gap-analysis.md](../../implementation-strategy/gap-analysis.md).

## Byline in this project

The project began as a strategy for creating the first practical WordPress implementation of the Byline spec. That objective is now partially realized:

- the plugin emits Byline data in RSS2, Atom, and JSON Feed
- the plugin is tested and CI-verified for core WordPress, Co-Authors Plus, and PublishPress Authors
- the plugin has a consumer-facing output reference

That changes the nature of this document. It is no longer "could this be implemented?" It is "what has the implementation taught us about the spec, and what still needs alignment?"

## What the current plugin validates

### 1. The adapter model is viable

WordPress multi-author data can be normalized once and routed to multiple feed formats from a common contract.

That is the most important architectural proof point in the repository:

- upstream authorship systems differ significantly
- the output layer can still stay format-focused and additive
- the plugin does not need to own author management to emit richer metadata

### 2. Byline works as an additive feed extension

The current implementation preserves standard feed elements while adding Byline metadata alongside them.

This matters because it confirms the core adoption premise:

- existing feed consumers are not broken
- richer identity can be layered onto ordinary feeds
- Byline output can be shipped before reader support exists

### 3. Perspective is implementable in real editorial workflows

The spec's `perspective` concept is not just theoretical. The plugin already demonstrates one practical way to collect and emit it from WordPress.

That is meaningful because `perspective` is one of the strongest parts of the spec's value proposition and one of the least likely things to appear automatically from upstream authorship plugins.

## Current spec-alignment issues

These are the important issues that remain before a stable 1.0 positioning.

### 1. Multi-author-per-item structure

This is the clearest structural divergence.

The current plugin emits repeated author/role pairs for multi-author items, but the current Byline spec is effectively singular at the item level. The implementation is useful, but the binding is positional rather than structurally explicit.

This should be treated as:

- a known current divergence
- a legitimate implementation finding
- something to resolve with the spec author before the plugin claims stable conformance

### 2. JSON Feed structure

The plugin's current JSON Feed output does not match the spec repo's example model exactly.

This is not a minor wording issue. It affects how author references are represented in JSON and therefore how a Byline-aware consumer would parse the feed.

This should also be resolved before stable 1.0 positioning.

### 3. Terminology drift

The spec still risks confusion around:

- `organization`
- `publication`
- `publisher`

The implementation experience has strengthened the case that these should not slide into each other casually in prose, because they affect how implementors think about entities, roles, and works.

## What is not a spec problem

The implementation has also clarified several things that should not be blamed on the Byline spec itself:

- unsupported-plugin behavior on WordPress sites that do not use supported adapters
- the current fediverse `attributedTo` ownership/authorship problem
- SEO-plugin coexistence problems that belong to the WordPress ecosystem rather than the feed spec

Those matter to the project, but they are not reasons to contort the Byline spec.

## Relationship to the current roadmap

The active post-Gate-A sequence is:

1. WP-04 `fediverse:creator`
2. WP-05 JSON-LD
3. WP-06 rights and AI consent
4. later adapter expansion such as Molongui

This spec-plan document should not override that order.

Its role is narrower:

- keep the Byline-specific implementation work honest
- document the current divergences
- make sure spec feedback is based on running code rather than conjecture

## HM Authorship in this context

HM Authorship still matters to the Byline story, but it no longer changes the roadmap as a future tranche because it now ships in the plugin.

It matters because:

- its upstream API is clean
- its data model maps closely to the normalized author contract
- it is likely the least awkward later adapter target

That makes it a useful supported integration and a good validation source for the normalized contract. It still does not make Authorship the center of the current spec plan.

### Why Authorship remains strategically useful

| Byline concept | Authorship source | Notes |
| --- | --- | --- |
| person name | `WP_User->display_name` | direct |
| person context | `WP_User->description` | direct |
| person URL | `WP_User->user_url` | direct |
| avatar | `get_avatar_url()` | direct |
| author ordering | `Authorship\\get_authors( $post )` | clean ordered array |
| role mapping | WordPress capabilities / guest-role logic | project-owned mapping |

Compared with taxonomy-driven plugins, Authorship still offers the cleanest upstream path once that tranche begins.

## Implementation stance going forward

### Keep one standalone plugin

The current project direction is a single standalone plugin with one normalized author layer and multiple outputs. That means this document should no longer recommend companion-module-first architecture as the default path.

### Keep the roadmap focused

Byline-spec work should not be allowed to expand the near-term scope into:

- broader graph modeling
- generic identity frameworks
- `did:web:` implementation
- ActivityPub protocol redesign

Those belong to the vision and research layers until the current roadmap is further along.

### Keep spec evidence concrete

The strongest future use of this document is as a record of concrete implementation evidence:

- what worked
- what required interpretation
- what diverged
- what upstream clarification would remove ambiguity

## Pre-1.0 Byline-specific priorities

1. Maintain the current feed implementation as the reference implementation candidate.
2. Keep the output reference and tests aligned with shipped behavior.
3. Continue spec-author discussion on:
   - multi-author-per-item structure
   - JSON Feed structure
   - terminology drift
4. Treat stable 1.0 wording as contingent on those issues being resolved or explicitly accepted as conscious divergences.

## Related documents

- [implementation-spec.md](implementation-spec.md) — active roadmap summary
- [implementation-strategy/implementation-spec.md](../../implementation-strategy/implementation-spec.md) — work packages, release gates, cross-cutting concerns
- [implementation-strategy/gap-analysis.md](../../implementation-strategy/gap-analysis.md) — current status and post-Gate-A priorities
- [byline-adoption-strategy.md](byline-adoption-strategy.md) — ecosystem strategy beyond spec conformance
