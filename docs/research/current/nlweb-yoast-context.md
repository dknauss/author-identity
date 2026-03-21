# NLWeb, Yoast Schema Aggregation, and byline-feed

## Overview

In May 2025, Microsoft launched NLWeb — an open protocol and reference implementation for
exposing natural-language query interfaces on websites. In November 2025, Yoast announced a
collaboration with the NLWeb team. In March 2026 (Yoast SEO v27.1), the first concrete result
shipped: Schema Aggregation, a single `/schemamap` endpoint that exposes a WordPress site's
entire schema entity graph for AI agent consumption via MCP (Model Context Protocol).

This document records how NLWeb and the Yoast schemamap fit into the byline-feed project's
picture, the field coverage comparison between the schemamap's author data and our normalized
author contract, and the adoption strategy implications.

For architectural decisions that follow from this analysis, see:
- `implementation-strategy/wp-05.md` — revised schema output work package
- `implementation-strategy/gap-analysis.md` / `known-gaps.md` — Schema output and AI agent
  discovery section

---

## The R.V. Guha thread

The NLWeb project is led by R.V. Guha, Corporate Vice President and Technical Fellow at
Microsoft. Guha co-created RSS (1999), co-created Schema.org (2011), and is now building the
agent query protocol that sits on top of both. NLWeb explicitly lists RSS and Schema.org as its
semantic foundation:

> "Schema.org and related semi-structured formats like RSS — used by over 100 million websites
> — have become not just de facto syndication mechanisms, but also a semantic layer for the web."
> — NLWeb README, nlweb-ai/NLWeb

This is not an adjacent development. The person who built the semantic layer byline-feed
enriches (Schema.org) and the syndication format it rides on (RSS) is building the agent query
layer on top of both. The enrichments byline-feed delivers to feed subscribers and HTML schema
consumers are precisely the enrichments NLWeb agents need.

---

## What NLWeb is

NLWeb has two components:

1. **A protocol** — a simple REST API (`/ask`) that accepts natural-language queries and returns
   responses as Schema.org JSON. Every NLWeb instance also acts as an MCP server, making site
   content queryable by any MCP-compatible AI agent (Copilot, Claude, etc.).

2. **A reference implementation** — Python-based, connecting to vector stores (Qdrant, Postgres,
   Elasticsearch, Snowflake, etc.) and LLMs (OpenAI, Anthropic, Gemini, DeepSeek). Ingests
   structured data from existing site markup.

The design philosophy: NLWeb is to MCP/A2A what HTML is to HTTP. It provides a standardized
content layer for AI agents in the same way HTML provided a standardized content layer for
browsers.

NLWeb is an open project (MIT license) released on GitHub at `nlweb-ai/NLWeb`. It is
platform-agnostic and is not a closed Microsoft ecosystem.

---

## What Yoast shipped: Schema Aggregation (March 2026)

Yoast SEO v27.1 introduced Schema Aggregation as an opt-in feature built in collaboration with
the NLWeb team. The core feature is a "schemamap" — a single endpoint that exposes a WordPress
site's entire Schema.org entity graph.

Key properties of the schemamap:

- **Entity deduplication.** Authors, organizations, and products referenced across multiple
  pages exist as single nodes. An agent does not need to crawl individual pages to assemble a
  picture of who writes for the site.

- **Relationship preservation.** Author nodes are linked to the articles they wrote;
  organization nodes are linked to their content and products. The graph carries relationships,
  not just entities.

- **Third-party extension.** Plugins that extend Yoast's Schema API — including The Events
  Calendar and WP Recipe Maker — are pulled into the schemamap automatically. byline-feed's
  WP-05 Yoast integration follows the same extension path.

- **NLWeb MCP endpoint.** The schemamap is the data source NLWeb's `ask` endpoint queries.
  When an AI agent asks "who are the expert authors on this site?" it is querying the schemamap.

- **Schema Visualizer.** Yoast also shipped a developer tool for inspecting the schemamap
  graph — useful for verifying that byline-feed enrichments appear correctly.

The feature is available in the free version of Yoast SEO with a settings toggle. Richer
data (e.g. WooCommerce product schema) is included automatically for paid Yoast plan users.

---

## The layer stack

Where byline-feed sits relative to the NLWeb query layer:

```
┌──────────────────────────────────────────────────────────┐
│  AI Agents / MCP Clients (Copilot, Claude, etc.)         │
├──────────────────────────────────────────────────────────┤
│  NLWeb  ←  /ask endpoint, Schema.org JSON responses      │
├──────────────────────────────────────────────────────────┤
│  Yoast Schema Aggregation  ←  /schemamap entity graph    │
│  (authors, articles, orgs, products — deduplicated)      │
├──────────────────────────────────────────────────────────┤
│  Schema.org structured data  ←  per-page JSON-LD         │
│  byline-feed WP-05 (Yoast mode) enriches this layer      │
├──────────────────────────────────────────────────────────┤
│  RSS / Atom / JSON Feed  ←  byline-feed primary output   │
│  (Byline spec: perspective, role, affiliation, rights)   │
├──────────────────────────────────────────────────────────┤
│  WordPress  ←  Co-Authors Plus, PublishPress, core WP    │
└──────────────────────────────────────────────────────────┘
```

byline-feed operates on two layers simultaneously:

- **Feed layer** — the primary output, enriching RSS/Atom/JSON Feed with the Byline spec.
- **Schema layer** — WP-05, enriching the Yoast schemamap with multi-author arrays, contributor
  roles, editorial perspective, fediverse identity, and AI consent signals.

These are different output channels consuming the same normalized author data. The adapter
pattern and contributor pre-pass ensure the single source of truth flows to both.

---

## Field coverage: Yoast schemamap vs. normalized author contract

The Yoast schemamap's `Person` nodes and byline-feed's normalized author object cover
overlapping but non-identical fields.

| Field | Yoast schemamap `Person` | byline-feed `normalized_author` | Notes |
|---|---|---|---|
| `@id` graph URI | ✅ | — | Yoast anchors entities in a graph; our objects are self-contained |
| `name` | ✅ | ✅ `display_name` | Direct match |
| `url` | ✅ | ✅ `url` | Direct match |
| `image` (`ImageObject`) | ✅ | ✅ `avatar_url` | We have the URL; Yoast wraps in `ImageObject` |
| `description` | ✅ | ✅ `description` | Direct match |
| `sameAs` | ✅ (from Yoast SEO settings UI) | ✅ `profiles[]` + `fediverse` | Sources differ — see below |
| `worksFor` / `affiliation` | ✅ (from Knowledge Graph settings) | — (Byline spec has `byline:affiliation`; not yet wired) | Future opportunity |
| Article `author` array | ⚠️ single `@id` ref | ✅ ordered multi-author array | **Critical gap in Yoast** |
| **`bylineRole`** | ❌ | ✅ `role` | `creator`, `editor`, `guest`, `staff`, `contributor`, `bot` |
| **`bylinePerspective`** | ❌ | ✅ per-post (WP-03) | `reporting`, `opinion`, `analysis`, `sponsored` |
| **`aiTrainingConsent`** | ❌ | ✅ `ai_consent` (WP-06) | `allow` / `deny` per author and per post |
| Live fediverse identity | ❌ | ✅ `fediverse` handle | Yoast's `sameAs` comes from settings, not multi-author plugin data |

**sameAs source difference.** Yoast populates `sameAs` from its SEO settings UI — values a
site admin enters manually. byline-feed populates `sameAs` from live multi-author plugin data
(Co-Authors Plus profiles, PublishPress Authors fields) plus fediverse handles stored in
WordPress user meta. On a site with 20 authors, Yoast's `sameAs` reflects whatever an admin
has typed; ours reflects what each author has actually configured. The WP-05 Yoast integration
merges both rather than choosing one, producing the most complete identity record.

**The multi-author Article gap.** Yoast's `Article` schema node references a single `Person`
via `@id` even on multi-author posts. This means the schemamap — and therefore NLWeb agents
querying it — sees single-author attribution for content with two or three bylines. byline-feed
WP-05 replaces this with a correctly ordered multi-author array.

---

## Adoption strategy implications

### The pitch sharpens

The existing byline-feed pitch to publishers is: structured author identity travels with your
work across feeds, search, and AI. The NLWeb/Yoast picture adds a concrete mechanism:

> The NLWeb agent querying your Yoast schemamap needs to know *who* wrote your content, in
> what *role*, with what *perspective*, and under what *consent terms*. Yoast knows who wrote
> things. byline-feed knows how they wrote things and what rights they carry. Together they
> give AI agents a complete, accurate picture — without that combination, agents get
> single-author attribution, no editorial context, and no consent signals.

This is a concrete, demonstrable gap. The Yoast Schema Visualizer makes it visible: before
byline-feed WP-05, a multi-author post shows one `Person` in the Article author field. After,
it shows an ordered array. The difference is inspectable in a browser.

### The Guha/Godier angle

Both R.V. Guha (NLWeb, Schema.org, RSS) and Terry Godier (Byline spec, Current app) are
working from the same premise — structured identity and content signals should be
machine-readable at the source, not reconstructed downstream by crawlers and models. Byline's
`perspective` element, which maps to Godier's velocity system for content aging, is the
item-level editorial signal that NLWeb agents need to reason about source reliability over
time. Neither Yoast's schemamap nor NLWeb's reference implementation has this — it is
byline-feed's distinctive contribution.

### WordPress scale argument

Yoast has 13 million active installs. byline-feed's WP-05 Yoast integration means byline
enrichment data flows into the NLWeb schemamap on any WordPress site that has both Yoast and
byline-feed active. The adoption path is extension of an existing, widely-deployed system —
not a greenfield deployment.

---

## References

- NLWeb repository: https://github.com/nlweb-ai/NLWeb
- Yoast + NLWeb announcement (November 2025): https://yoast.com/press/yoast-nlweb-microsoft-251125/
- Yoast Schema Aggregation launch (March 2026): https://yoast.com/yoast-seo-march-3-2026/
- Yoast NLWeb integration page: https://yoast.com/integrations/nlweb/
- Yoast Schema Aggregation coverage (Search Engine Journal, March 2026):
  https://www.searchenginejournal.com/yoast-seos-new-schema-aggregator-improves-entity-disambiguation/568764/
- Yoast scaling the agentic web: https://yoast.com/scaling-the-agentic-web-with-nlweb/
