# Protocol and Specification Coverage Map

How author identity travels — or fails to travel — across output channels, and which protocols carry which signals.

This document maps the protocols and standards relevant to portable author identity in WordPress. It is a **coverage map**, not a proposal for a unified spec. The [Byline Feed plugin](../../byline-feed/) is the unifying layer — the plugin routes author data from a single WordPress source of truth to whichever protocol fits the channel. The protocols themselves remain independent and complementary.

For how the WordPress multi-author plugins that feed these protocols compare architecturally, see [multi-author-matrix.md](multi-author-matrix.md). For the full vision of where all of this is heading, see [author-identity-vision.md](../vision/author-identity-vision.md).

---

## The five output channels

Author identity leaves a WordPress site through five distinct channels. Each channel has different consumers, different trust models, and different protocols. No single spec covers all five.

| Channel | What travels through it | Who consumes it | Protocols |
| --- | --- | --- | --- |
| **Syndication feeds** | Structured author metadata in RSS/Atom/JSON Feed | Feed readers, aggregators, AI search | Byline, RSS 2.0, Atom, Dublin Core, JSON Feed |
| **HTML head** | Schema markup, social tags, fediverse identity | Search engines, social platforms, fediverse servers | JSON-LD/schema.org, OpenGraph, fediverse:creator, microformats2 |
| **HTTP headers** | Rights declarations, crawl directives | AI crawlers, data miners, bots | TDM-Rep, robots meta |
| **Well-known files** | Site-wide policies | Crawlers, AI training pipelines | ai.txt, robots.txt |
| **Federation** | Signed activity objects with actor attribution | Fediverse servers (Mastodon, Pixelfed, etc.) | ActivityPub, HTTP Signatures, WebFinger |

---

## Identity signal coverage

Which protocols carry which identity signals. This is the core of the coverage map.

### Author identity signals

| Signal | Byline (feeds) | JSON-LD (HTML) | ActivityPub (federation) | fediverse:creator (HTML) | OpenGraph (HTML) | h-card (HTML) | RSS 2.0 | Atom | JSON Feed |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| **Display name** | ✅ `byline:name` | ✅ `Person.name` | ✅ `actor.name` | ❌ | ✅ `og:article:author` | ✅ `p-name` | ⚠️ Email only | ✅ `author/name` | ✅ `author.name` |
| **Bio/description** | ✅ `byline:context` | ✅ `Person.description` | ✅ `actor.summary` | ❌ | ❌ | ✅ `p-note` | ❌ | ❌ | ❌ |
| **Profile URL** | ✅ `byline:url` | ✅ `Person.url` | ✅ `actor.url` | ❌ | ❌ | ✅ `u-url` | ❌ | ✅ `author/uri` | ✅ `author.url` |
| **Avatar** | ✅ `byline:avatar` | ✅ `Person.image` | ✅ `actor.icon` | ❌ | ❌ | ✅ `u-photo` | ❌ | ❌ | ✅ `author.avatar` |
| **Social profiles** | ✅ `byline:profile` | ✅ `Person.sameAs` | ❌ | ❌ | ❌ | ✅ `rel="me"` links | ❌ | ❌ | ❌ |
| **Interpersonal relationships** | ❌ | ✅ `Person.knows` | ✅ Follow/follower graph | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Fediverse handle** | ❌ | ❌ | ✅ (actor URI) | ✅ `@user@instance` | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Multiple authors** | ✅ Multiple `byline:person` | ✅ `author` array | ⚠️ Single `attributedTo` | ⚠️ Multiple tags, first displayed | ❌ | ✅ Multiple `h-card` | ❌ | ✅ Multiple `author` | ✅ `authors` array |

> **Note on XFN:** Interpersonal relationships are uniquely covered by [XFN 1.1](#xfn-xhtml-friends-network) via `rel` attribute values on HTML links (friend, colleague, met, etc.). XFN is the origin of `rel="me"` and has been in WordPress core since 2.2 (2007). See the [XFN entry](#xfn-xhtml-friends-network) below and the [convergences section](#where-xfn-fits) for how it relates to Byline profile links.

### Content context signals

| Signal | Byline (feeds) | JSON-LD (HTML) | ActivityPub (federation) | Dublin Core (feeds) | OpenGraph (HTML) |
| --- | --- | --- | --- | --- | --- |
| **Author role** | ✅ `byline:role` | ⚠️ `Person.jobTitle` | ❌ | ❌ | ❌ |
| **Content perspective** | ✅ `byline:perspective` | ❌ | ❌ | ❌ | ❌ |
| **Affiliation/COI** | ✅ `byline:affiliation` | ✅ `Person.worksFor` | ❌ | ❌ | ❌ |
| **Organization** | ✅ `byline:org` | ✅ `publisher` | ❌ | ❌ | ❌ |
| **Content type** | ❌ | ✅ `@type` (Article, NewsArticle, etc.) | ✅ Object type | ✅ `dc:type` | ✅ `og:type` |

### Rights and consent signals

| Signal | robots meta (HTML) | TDM-Rep (HTTP) | ai.txt (file) | Byline (feeds) | CC license (feeds) | C2PA (media) |
| --- | --- | --- | --- | --- | --- | --- |
| **AI training opt-out** | ✅ `noai` | ✅ Policy URL | ✅ Directives | ❌ (future) | ❌ | ✅ Signed |
| **Image AI opt-out** | ✅ `noimageai` | ❌ | ❌ | ❌ | ❌ | ✅ Signed |
| **License declaration** | ❌ | ❌ | ❌ | ❌ | ✅ `cc:license` | ✅ Manifest |
| **Per-author consent** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Content provenance** | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ Signed chain |

### Verification and trust

| Signal | XFN/rel="me" (HTML) | ActivityPub (federation) | HTTP Signatures | WebFinger | fediverse:creator | Byline (feeds) | JSON-LD (HTML) |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **Identity verification** | ✅ Mutual linking (`me`) | ✅ Cryptographic | ✅ Cryptographic | ✅ Domain-based | ✅ Domain allowlist | ❌ Declared only | ❌ Declared only |
| **Relationship graph** | ✅ Full XFN vocabulary | ✅ Follow/follower | ❌ | ❌ | ❌ | ❌ | ⚠️ `Person.knows` |
| **Tamper detection** | ❌ | ✅ Signed objects | ✅ Signed requests | ❌ | ❌ | ❌ | ❌ |
| **Trust model** | Social proof | Cryptographic | Cryptographic | Domain control | Domain allowlist | Publisher trust | Publisher trust |

---

## Protocol inventory

### Feed protocols

#### Byline Specification
- **Version:** v0.1.0 (January 2026, CC0 licensed)
- **URL:** bylinespec.org
- **Channel:** RSS 2.0, Atom, JSON Feed (via XML namespace `xmlns:byline="https://bylinespec.org/1.0"`)
- **What it adds:** Structured author identity (`byline:person`), content perspective (`byline:perspective`), author roles (`byline:role`), affiliation/COI disclosure, IndieWeb profile references (`/now`, `/uses`)
- **Design:** Additive — never removes or replaces standard feed elements. Ignored by readers that don't support it.
- **Trust model:** Declared metadata. No built-in verification, but profile links enable `rel="me"` mutual linking as a social-proof layer.
- **WordPress status:** No existing implementation. Byline Feed plugin will be the first. See [implementation-spec.md](../planning/implementation-spec.md).

#### RSS 2.0
- **Standard:** Stable (Dave Winer / Harvard Law)
- **Channel:** XML syndication feeds
- **Author support:** Single `<author>` element per item containing email address. `<managingEditor>` at channel level.
- **Limitations:** No structured author profiles, no multi-author support, no role/perspective/rights metadata.
- **WordPress status:** Core feature (`/feed/rss2/`). Byline Feed preserves all standard elements.

#### Atom (RFC 4287)
- **Standard:** IETF RFC 4287
- **Channel:** XML syndication feeds
- **Author support:** `<author>` element with `<name>`, `<uri>`, `<email>` children. Supports `<contributor>` for additional authors.
- **Advantages over RSS 2.0:** Structured author sub-elements, multiple contributor support.
- **WordPress status:** Core feature (`/feed/atom/`). Byline Feed extends with parallel implementation.

#### Dublin Core
- **Standard:** DCMI Metadata Terms (`xmlns:dc="http://purl.org/dc/elements/1.1/"`)
- **Channel:** RSS/Atom feeds, HTML meta tags
- **Author support:** `dc:creator` element. Also `dc:contributor`, `dc:rights`, `dc:license`.
- **WordPress status:** WordPress RSS2 feeds include `dc:creator`. Byline Feed preserves alongside new elements.

#### JSON Feed
- **Version:** v1.1 (jsonfeed.org)
- **Channel:** JSON syndication feeds
- **Author support:** `author` object with `name`, `url`, `avatar`. `authors` array for multiple. Most capable native multi-author support of any feed format.
- **WordPress status:** Not in core. Byline spec supports JSON Feed but WordPress implementation is deferred past MVP.

### HTML head protocols

#### JSON-LD / schema.org
- **Standard:** W3C Recommendation (schema.org, continuously updated)
- **Channel:** HTML `<script type="application/ld+json">` in `wp_head`
- **Author support:** `Person` type with `name`, `url`, `description`, `image`, `sameAs` (social profiles), `worksFor` (organization). `Article.author` accepts array for multi-author.
- **Consumers:** Google (E-E-A-T ranking signals, Knowledge Panels, Rich Results), Bing, AI search systems.
- **WordPress status:** Theme-dependent. SEO plugins (Yoast, Rank Math) provide single-author schema. Multi-author JSON-LD is rare. Byline Feed [WP-05](../../Implementation%20Strategy/wp-05.md) will address.

#### fediverse:creator
- **Standard:** Informal convention (Mastodon, July 2024)
- **Channel:** HTML `<meta name="fediverse:creator" content="@user@instance" />`
- **Purpose:** When a link is shared on Mastodon, the author's fediverse identity appears on the link preview card.
- **Verification:** Author must add the publishing domain to their Mastodon "Author Attribution" allowlist. This is domain-based verification, stronger than pure declared metadata.
- **Multi-author:** Mastodon currently displays only the first tag. PR #30846 adds multi-author API support; display support expected to follow.
- **WordPress status:** No automated generation. Byline Feed [WP-04](../../Implementation%20Strategy/wp-04.md) will output from user meta.

#### OpenGraph
- **Standard:** De facto (Facebook/Meta, ogp.me)
- **Channel:** HTML meta tags (`<meta property="og:..." />`)
- **Author support:** `og:article:author` (name or URL). No structured profile data.
- **Consumers:** Facebook, LinkedIn, WhatsApp, Discord, link preview systems.
- **WordPress status:** SEO plugins provide. Not in scope for Byline Feed (SEO plugins handle adequately).

#### Microformats2
- **Standard:** Community standard (microformats.org)
- **Channel:** HTML semantic markup (visible page content with class annotations)
- **Author support:** `h-card` (person/org profile), `h-entry` (post/article with `p-author`). Rich profile data: `p-name`, `p-note`, `u-photo`, `u-url`, social links via `rel="me"`.
- **Trust model:** Mutual `rel="me"` linking provides social-proof verification.
- **WordPress status:** IndieWeb plugin provides. Not in MVP scope for Byline Feed; listed as Component 5 (IndieWeb integration) in the vision doc.

### Rights and consent protocols

#### robots meta (noai / noimageai)
- **Standard:** Google convention (2024)
- **Channel:** HTML `<meta name="robots" content="noai, noimageai" />`
- **Purpose:** Advisory signal requesting AI crawlers not use page content for training.
- **Enforcement:** None — depends on crawler compliance. Not legally binding.
- **WordPress status:** Not in core. Byline Feed [WP-06](../../Implementation%20Strategy/wp-06.md) will output per-post when any attributed author has `ai_consent = 'deny'`.

#### TDM-Rep (Text and Data Mining Reservation)
- **Standard:** W3C emerging specification
- **Channel:** HTTP response header (`TDMRep: https://example.com/tdm-policy`)
- **Purpose:** Declares a policy URL governing text and data mining rights. More formal than robots meta; on a W3C standardization path that may eventually carry legal weight.
- **WordPress status:** Not in core. Byline Feed [WP-06](../../Implementation%20Strategy/wp-06.md) will output header.

#### ai.txt
- **Standard:** Emerging community convention (analogous to robots.txt)
- **Channel:** Well-known file at `/ai.txt` or `/.well-known/ai.txt`
- **Purpose:** Site-wide AI training consent policy. Machine-readable directives per content category.
- **WordPress status:** Not in core. Byline Feed [WP-06](../../Implementation%20Strategy/wp-06.md) will dynamically generate.

#### Creative Commons
- **Standard:** Creative Commons (creativecommons.org)
- **Channel:** RSS/Atom feeds (`<cc:license>`), HTML meta/link tags, JSON-LD `license` property
- **Purpose:** Machine-readable license declaration with legal enforceability.
- **WordPress status:** Available via plugins. Byline Feed may reference in WP-06.

#### C2PA (Content Provenance and Authenticity)
- **Standard:** Industry consortium (c2pa.org), ISO/IEC standardization in progress
- **Channel:** Manifest metadata embedded in media files (images, video; text support nascent)
- **Purpose:** Cryptographically signed content provenance — creator identity, modification history, AI-generation labeling. The strongest trust model of any spec listed here.
- **WordPress status:** Not applicable to text content yet. Monitored for evolution. Referenced in [author-identity-vision.md](../vision/author-identity-vision.md) as a forward-looking convergence point.

### Federation protocols

#### ActivityPub
- **Standard:** W3C Recommendation
- **Channel:** Server-to-server JSON-LD activity delivery
- **Author support:** `attributedTo` field on activity objects pointing to an Actor. Actor has `name`, `summary`, `url`, `icon`, `publicKey`.
- **Trust model:** Cryptographic HTTP Signatures on every activity. The strongest online verification model — receiving servers verify the origin server's signature.
- **Multi-author:** Single `attributedTo` per spec. Multi-author support has been discussed (FEP #2358 was rejected); no consensus yet.
- **Relationship to Byline:** Solves the same problem (portable author identity) in a different distribution context. Byline addresses syndication feeds; ActivityPub addresses federation. A plugin populating both from the same WordPress data gives writers a consistent identity across both channels.
- **WordPress status:** WordPress ActivityPub plugin (Automattic, maintained by Matthias Pfefferle) exists but is not core. Multi-author support is incomplete due to `post_author` constraint. See [author-identity-vision.md § ActivityPub convergence](../vision/author-identity-vision.md#activitypub-convergence-and-tension).

#### HTTP Signatures
- **Standard:** IETF Internet-Draft (draft-ietf-httpbis-message-signatures)
- **Channel:** HTTP request headers on ActivityPub deliveries
- **Purpose:** Cryptographic verification that an activity actually originated from the server that claims to have sent it.
- **WordPress status:** Handled by the WordPress ActivityPub plugin for outgoing activities.

#### WebFinger (RFC 7033)
- **Standard:** IETF RFC 7033
- **Channel:** `/.well-known/webfinger` endpoint (JSON-LD response)
- **Purpose:** Account discovery — resolves `@user@domain` to an actor profile URL. Foundation for fediverse identity lookup.
- **WordPress status:** WordPress ActivityPub plugin provides.

#### NodeInfo
- **Standard:** Community standard (nodeinfo.diaspora.software, v2.1)
- **Channel:** `/.well-known/nodeinfo` endpoint
- **Purpose:** Server-level metadata (software, user counts, features). Not author-level identity, but part of the federation discovery stack.
- **WordPress status:** WordPress ActivityPub plugin may provide.

### Identity verification and social graph

#### XFN (XHTML Friends Network)
- **Standard:** XFN 1.1 (gmpg.org/xfn/11, 2004, CC-BY-SA)
- **Channel:** HTML `rel` attribute on `<a>` and `<link>` elements
- **Purpose:** Declares interpersonal relationships via hyperlinks. The only protocol in this inventory that describes *how people relate to each other* rather than who they are or what they created.
- **Relationship vocabulary:** Friendship (contact, acquaintance, friend), physical (met), professional (co-worker, colleague), geographical (co-resident, neighbor), family (child, parent, sibling, spouse, kin), romantic (muse, crush, date, sweetheart), identity (me). Categories have mutual-exclusivity rules.
- **Trust model:** Declared metadata on links. No verification mechanism for most values. The `me` value is the exception — it enables bidirectional mutual-linking verification (see `rel="me"` below).
- **WordPress lineage:** XFN has been part of WordPress core since version 2.2 (2007). The original Links Manager stored XFN values in `wp_links.link_rel`. The Links Manager was hidden in WP 3.5 (2012) but never removed. The [Link Extension for XFN](https://wordpress.org/plugins/link-extension-for-xfn/) plugin (Courtney Robertson, 2025) restores the full XFN 1.1 vocabulary to the block editor link UI, adding relationship metadata to any block that supports links.
- **Relevance to this project:** XFN is the origin of `rel="me"`, which is foundational to IndieWeb identity verification and fediverse profile linking. The Byline spec's `byline:profile` element carries `rel` attributes — these could include XFN values, giving feed readers a richer graph of author relationships. See [convergences § Where XFN fits](#where-xfn-fits).

#### rel="me"
- **Standard:** XFN 1.1 value, adopted as HTML standard link relation (microformats.org, HTML spec)
- **Channel:** HTML `<link rel="me">` or `<a rel="me">` elements
- **Origin:** The `me` value in XFN 1.1 (see above). Exclusive of all other XFN values — it means "this link points to another representation of me."
- **Purpose:** Bidirectional identity verification. If your WordPress site links to your Mastodon profile with `rel="me"`, and your Mastodon profile links back to your WordPress site, both sides have verified the connection.
- **Trust model:** Mutual linking — stronger than declared metadata, weaker than cryptographic. Requires checking both directions.
- **WordPress status:** Themes can output. Byline Feed will enable via profile link output. The Byline spec's `byline:profile` elements can carry `rel` attributes for this purpose.

---

## How the Byline Feed plugin routes identity across channels

The plugin is the **routing layer** — not a protocol. It reads author data once (via the [adapter layer](multi-author-matrix.md#adapter-coverage-in-byline-feed)) and writes it to multiple output channels using the appropriate protocol for each.

| Work package | Output channel | Protocol used | Identity signals routed |
| --- | --- | --- | --- |
| [WP-01](../../Implementation%20Strategy/wp-01.md) | Internal | Adapter interface | Normalized author object from CAP/PPA/Core |
| [WP-02](../../Implementation%20Strategy/wp-02.md) | RSS2 + Atom feeds | Byline namespace | Name, bio, avatar, URL, role, author refs |
| [WP-03](../../Implementation%20Strategy/wp-03.md) | RSS2 + Atom feeds | Byline namespace | Content perspective |
| [WP-04](../../Implementation%20Strategy/wp-04.md) | HTML `<head>` | fediverse:creator | Fediverse handle per author |
| [WP-05](../../Implementation%20Strategy/wp-05.md) | HTML `<head>` | JSON-LD / schema.org | Person objects, sameAs links, Article schema |
| [WP-06](../../Implementation%20Strategy/wp-06.md) | HTML + HTTP + file | robots meta, TDM-Rep, ai.txt | AI consent, rights declarations |

This is why a unified spec would be the wrong abstraction. Each protocol operates in a different channel with different consumers and different trust assumptions. The plugin normalizes the *data*; the protocols remain purpose-built for their channels.

---

## Convergences and tensions

### Where protocols agree

All protocols that carry author identity agree on the same core fields: **name**, **profile URL**, and some form of **bio/description**. These map cleanly:

| Core field | Byline | JSON-LD | ActivityPub | h-card |
| --- | --- | --- | --- | --- |
| Name | `byline:name` | `Person.name` | `actor.name` | `p-name` |
| Bio | `byline:context` | `Person.description` | `actor.summary` | `p-note` |
| URL | `byline:url` | `Person.url` | `actor.url` | `u-url` |
| Avatar | `byline:avatar` | `Person.image` | `actor.icon` | `u-photo` |

The Byline Feed plugin's [normalized author object](../planning/implementation-spec.md#normalized-author-object-contract) maps to all four without loss.

### Where protocols diverge

**Verification models.** This is the fundamental tension. Three distinct trust levels coexist:

1. **Cryptographic** (ActivityPub, HTTP Signatures, C2PA) — the origin server signs every object. Receiving servers verify. Strongest guarantee.
2. **Mutual linking** (rel="me", fediverse:creator domain allowlist) — social proof. Both sides link to each other. Moderate guarantee.
3. **Declared metadata** (Byline, JSON-LD, OpenGraph, Dublin Core, robots meta) — the publisher says it, consumers trust it. Weakest guarantee but simplest to implement and broadest reach.

These are not incompatible. A sophisticated implementation uses cryptographic identity (ActivityPub) as the strong layer, mutual linking (rel="me") as the human-readable discovery layer, and declared metadata (Byline, JSON-LD) as the broadest-reach distribution layer. The [vision document](../vision/author-identity-vision.md#activitypub-convergence-and-tension) describes this layering.

**Multi-author support.** Feed protocols handle it well (Byline, Atom, JSON Feed all support multiple authors natively). ActivityPub does not — `attributedTo` is effectively single-author, and proposals for multi-author federation have stalled (FEP #2358 rejected). The fediverse:creator meta tag supports multiple tags but Mastodon currently displays only the first. This is a real interop gap with no protocol-level solution yet.

**Content perspective.** Only the Byline spec carries it. No other protocol has a concept of editorial intent (reporting vs. opinion vs. satire). This is Byline's most distinctive contribution — it addresses a problem (content collapse in feeds) that no other standard even recognizes.

**Rights and consent.** Fragmented across four mechanisms (robots meta, TDM-Rep, ai.txt, CC license) with no coordination between them. None support per-author granularity — they're all per-page or per-site. The Byline Feed plugin's [WP-06](../../Implementation%20Strategy/wp-06.md) routes per-author consent preferences to these per-page outputs using a most-restrictive-wins rule: if any author on a multi-author post denies AI training, the page-level signal is deny.

### Where XFN fits

XFN occupies a unique position in this landscape. Every other protocol answers "who is this author?" or "what did they create?" XFN answers **"how does this person relate to other people?"** — friend, colleague, co-worker, met in person, family. It's a social graph protocol, not an identity or attribution protocol.

This matters for the Byline Feed project in three ways:

1. **`rel="me"` is an XFN value.** The identity verification mechanism that underpins IndieWeb linking, Mastodon profile verification, and Byline profile links originated in XFN 1.1 (2004). WordPress has carried XFN support since version 2.2 (2007), making it one of the oldest web standards in WordPress core.

2. **Byline profile links can carry XFN values.** The Byline spec's `byline:profile` element has `href` and `rel` attributes. A feed entry could declare `<byline:profile href="https://example.com/editor" rel="colleague" />`, telling feed readers not just that the author links to this person, but *how* they relate. This is richer than `sameAs` (which only says "same person, different URL") and could surface meaningful editorial context — "this reporter's colleague reviewed this piece."

3. **WordPress already has the infrastructure.** The [Link Extension for XFN](https://wordpress.org/plugins/link-extension-for-xfn/) plugin (2025) restores the full XFN 1.1 vocabulary to the block editor. If authors are already annotating their links with XFN relationships, the Byline Feed plugin could harvest those `rel` values and carry them into feed output — no new data entry required. This is the same pattern as the adapter layer: normalize existing data into structured output.

XFN is not on the Byline Feed MVP roadmap, but it's a natural extension point for the IndieWeb integration planned as Component 5 in the [vision document](../vision/author-identity-vision.md). For how relationships fit into the broader authorship framework alongside attribution, control, provenance, and rights, see [author-identity-vision.md § Relationships](../vision/author-identity-vision.md#relationships).

---

## What this means for implementation

The coverage map reveals why the Byline Feed plugin is structured as a routing layer with work-package-per-channel:

1. **No single protocol covers all channels.** You need Byline for feeds, JSON-LD for search, fediverse:creator for Mastodon, and robots/TDM-Rep/ai.txt for rights. Trying to unify these into one spec would either lose channel-specific capabilities or add complexity that each channel's consumers wouldn't understand.

2. **The data model is the shared layer, not the protocol.** The [normalized author contract](../planning/implementation-spec.md#normalized-author-object-contract) is what unifies. It captures the superset of fields across all protocols; each output module selects what it needs.

3. **Trust is layered, not unified.** A Byline `byline:person` element carries declared metadata (weakest trust). The same author's fediverse:creator tag has domain-verified trust (moderate). Their ActivityPub actor has cryptographically signed trust (strongest). The *data* is the same; the *assurance level* varies by channel. This is correct — different channels serve different purposes.

4. **Gaps are protocol-specific, not systemic.** Content perspective is a Byline-only concept because no other channel needs it (search engines don't distinguish reporting from opinion; fediverse servers don't either). Per-author AI consent has no protocol support anywhere — the plugin synthesizes it from author-level preferences into page-level signals. These gaps don't indicate a missing unified spec; they indicate capabilities that are genuinely specific to certain channels.

---

## Related documents

- [multi-author-matrix.md](multi-author-matrix.md) — Plugin implementation comparison (the systems that produce author data)
- [author-identity-vision.md](../vision/author-identity-vision.md) — Full vision: feeds, schema, fediverse, AI, rights
- [implementation-spec.md](../planning/implementation-spec.md) — Byline Feed plugin spec and work packages
- [byline-spec-plan.md](../planning/byline-spec-plan.md) — Byline RSS spec assessment
- [byline-adoption-strategy.md](../planning/byline-adoption-strategy.md) — Cross-plugin adoption strategy
- [architecture.md](architecture.md) — HM Authorship source-level review
- [landscape.md](landscape.md) — Multi-author plugin landscape
- [known-gaps.md](known-gaps.md) — Security and data integrity gaps
- [Implementation Strategy/](../../Implementation%20Strategy/) — Work package specs (WP-01 through WP-06)
