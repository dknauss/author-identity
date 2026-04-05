# Research Index

This folder contains two different kinds of research:

1. **Current planning references** that directly inform the `byline-feed` roadmap.
2. **Exploratory semantic-publishing notes** that strengthen the long-term rationale for later work but do not justify expanding the near-term plugin scope yet.

## Current planning references

These are the documents that should remain linked from the main README and planning docs.

| File | Why it matters now |
| --- | --- |
| [`current/multi-author-matrix.md`](current/multi-author-matrix.md) | Core comparison of supported and target multi-author systems |
| [`current/protocol-coverage-map.md`](current/protocol-coverage-map.md) | Maps output channels and protocol responsibilities across feeds, HTML, headers, and federation |
| [`current/architecture.md`](current/architecture.md) | Source-grounded HM Authorship architecture and API surface |
| [`current/landscape.md`](current/landscape.md) | Ecosystem sizing, plugin prioritization, and historical lineage |
| [`current/known-gaps.md`](current/known-gaps.md) | HM Authorship risks and hardening notes |
| [`current/metadata-models-for-publishers.md`](current/metadata-models-for-publishers.md) | Best background input for WP-05 JSON-LD and future publication/organization modeling |
| [`current/canonical-author-identity-model.md`](current/canonical-author-identity-model.md) | Recommended internal source model based on the protocol and scholarly metadata research |
| [`current/authorship-architecture.mermaid`](current/authorship-architecture.mermaid) | Diagram source for the HM Authorship architecture review |

## Exploratory semantic-publishing notes

These are worth keeping, but they should be de-emphasized in repo navigation until the project explicitly opens a semantic-publishing track.

| File | Current status |
| --- | --- |
| [`exploratory/author-identity-graph-spec.md`](exploratory/author-identity-graph-spec.md) | Exploratory identity-graph sketch; overlaps heavily with `semantic-author-identity-model.md` |
| [`exploratory/semantic-author-identity-model.md`](exploratory/semantic-author-identity-model.md) | Exploratory graph model; overlaps heavily with `author-identity-graph-spec.md` |
| [`exploratory/crossref-spar-wordpress-graph.md`](exploratory/crossref-spar-wordpress-graph.md) | Research input for scholarly/ontology mapping; overlaps with `wordpress-semantic-publishing-architecture.md` |
| [`exploratory/wordpress-semantic-publishing-architecture.md`](exploratory/wordpress-semantic-publishing-architecture.md) | Broader semantic-publishing architecture note; not a current plugin implementation plan |
| [`exploratory/publishing-metadata-ecosystem.md`](exploratory/publishing-metadata-ecosystem.md) | Background survey; substantially overlaps with `current/metadata-models-for-publishers.md` |

## De-emphasis and merge notes

These docs are not wrong, but several clusters are duplicative enough that they should be treated as merge candidates rather than first-class roadmap inputs:

- `exploratory/author-identity-graph-spec.md` + `exploratory/semantic-author-identity-model.md`
  - likely future merge target: one concise author/publication graph concept note
- `exploratory/crossref-spar-wordpress-graph.md` + `exploratory/wordpress-semantic-publishing-architecture.md`
  - likely future merge target: one semantic-publishing architecture note
- `exploratory/publishing-metadata-ecosystem.md` + `current/metadata-models-for-publishers.md`
  - the exploratory file should stay secondary unless it grows beyond the current model survey

## Scope note

The exploratory set strengthens the long-term rationale for:

- WP-05 JSON-LD
- persistent identifiers later (`ORCID`, `ROR`, `DOI`)
- an eventual publication/organization model beyond just author bylines

But that research does **not** justify expanding the near-term plugin scope yet. Near-term execution remains:

1. WP-04
2. WP-05
3. HM Authorship adapter tranche
4. later protocol and metadata expansion as the implementation proves out
