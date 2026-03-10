# Documentation Index

Technical documentation for the Authorship fork. This is a historical index snapshot.

Most links below point to documents in the Authorship fork repo and are not included in this folder tree.

## Architecture and internals

- **Architecture Reference** *(external to this repo snapshot)* — Source-verified walkthrough of the data model, query rewriting, capability mapping, guest author mechanics, REST API, and file structure. Based on direct code audit of the `develop` branch (v0.2.17).

## Competitive landscape

- **Multi-Author Plugin Landscape** *(external to this repo snapshot)* — Comparison of Co-Authors Plus, PublishPress Authors, Molongui Authorship, WP Post Author, Simple Author Box, and Authorship. Includes active install counts from wp.org (March 2026), architectural approaches, feature comparison matrix, and historical lineage from Mark Jaquith's 2005 "Multiple Authors" through the present.

## Planned enhancements

- **[Byline Spec Assessment and Implementation Plan](byline-spec-plan.md)** — Analysis of the Byline open specification (bylinespec.org) for structured author identity in syndication feeds. Includes mapping from Authorship's data model to Byline elements, phased implementation plan, and strategic considerations for early adoption.

- **[Byline Feed Plugin — Cross-Plugin Adoption Strategy](LEGACY-byline-adoption-strategy.md)** — Strategy for a standalone wp.org plugin that outputs Byline-structured feed data across the WordPress multi-author plugin ecosystem. Covers the adapter architecture for Co-Authors Plus, PublishPress Authors, Molongui, and core WordPress; the addressable audience (~40K+ multi-author sites); perspective metadata; role mapping; and a phased adoption roadmap targeting both the supply side (WordPress feeds) and demand side (feed reader developers).

## Quality and security

- **Known Gaps and Security Notes** *(external to this repo snapshot)* — Security findings (guest author login, username normalization), data integrity concerns (post_author divergence, silent failures), performance notes, feed limitations, and compatibility considerations.

## Audit artifacts (from Phase 01)

These documents were produced during the initial code audit and define the quality baseline and build queue:

- **HM vs WPCS Audit** *(external to this repo snapshot)* — Repo-grounded standards audit with command evidence, rule references, and five detailed follow-up items with patch scaffolds.
- **Foundation Quality Baseline** *(external to this repo snapshot)* — Support matrix, green gate definition, and CI/local parity rules.
- **Phase 01 Roadmap** *(external to this repo snapshot)* — Build queue ordering and current state.

### Patch scaffolds

- **01-02 Standards Tooling** *(external to this repo snapshot)* — PHPCS/PHPStan refresh for modern PHP compatibility.
- **01-02 Security Hardening** *(external to this repo snapshot)* — Guest author username normalization and filter scope.
- **01-02 Observability** *(external to this repo snapshot)* — Post-insert failure signaling.
- **01-02 Performance** *(external to this repo snapshot)* — Editor component and CLI migration cleanup.
