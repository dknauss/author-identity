# Byline Spec — First Implementor's Notes

**From:** Byline Feed Plugin for WordPress (byline-feed)
**Spec version reviewed:** Byline v0.1.0 (January 2026)
**Date:** March 2026

---

## Context

These notes come from building the first known implementation of the Byline specification — a WordPress plugin that outputs Byline-structured author identity metadata across RSS2, Atom, and JSON Feed. The plugin uses an adapter pattern to normalize multi-author data from Co-Authors Plus (~20K sites), PublishPress Authors (~20K sites), and core WordPress (millions of sites), then feeds that normalized data into format-specific output layers.

This document covers: where the plugin conforms to and diverges from Byline v0.1.0, the multi-author-per-item question (non-conformant, not a spec bug), the JSON Feed structural divergence between spec and plugin models, the relationship between `perspective` and feed reader UX (specifically content velocity), and the contributor pre-pass pattern that every feed generator will need.

These are offered as constructive feedback from an implementor who wants the spec to succeed.

---

## Conformance status

The byline-feed plugin is a **mixed implementation** of Byline v0.1.0:

| Area | Status | Notes |
| --- | --- | --- |
| XML person/contributor model | **Conformant** | RSS2 and Atom output matches spec structure |
| Perspective element | **Conformant** | Per-item `byline:perspective` with spec vocabulary |
| Profile, now, uses elements | **Conformant** | Optional identity fields emitted per spec |
| Multi-author per item | **Non-conformant** | Plugin emits multiple `byline:author ref` per item; spec defines singular author per item |
| JSON Feed structure | **Divergent** | Plugin nests `_byline` on JSON Feed `authors[]` entries; spec uses `_byline.contributors[]` at feed level and scalar `_byline.author` ref per item |
| Item-level role with multiple authors | **Ambiguous** | Plugin emits one `byline:role` after each `byline:author ref` positionally; no structural binding groups them, so parsers may misinterpret the sequence |

This document separates three concerns throughout:
1. **What v0.1.0 specifies** — the spec as written and exampled
2. **Where the plugin intentionally diverges** — known non-conformances with rationale
3. **What we propose changing** — suggestions for future spec revisions

---

## 1. Multi-author-per-item: beyond spec scope, not a spec bug

### What the spec defines

After studying all four spec examples (`multi-author.atom.xml`, `corporate-blog.json`, `minimal.rss.xml`, `personal-blog.rss.xml`) and the spec text, Byline is designed for **one author per item**:

- Every example uses exactly one `<byline:author ref="..."/>` per entry/item.
- The JSON Feed mapping uses `"author": "..."` as a **scalar string**, not an array.
- The spec describes `<byline:author>` as "Links an item to its author" (singular).
- `<byline:role>` describes "The author's relationship to the content" (singular possessive).

The `multi-author.atom.xml` example is "multi-author" in the sense of multiple contributors across the **feed** (two entries, each with a different author), not multiple authors on a single entry.

`<byline:role>` as a per-item sibling of `<byline:author>` is unambiguous when there is one author per item. There is no role-per-author ambiguity in the spec as written.

### What this means for our plugin

WordPress supports multiple authors per post via Co-Authors Plus and PublishPress Authors. Our adapter layer normalizes these into an array. The plugin currently emits multiple `byline:author ref` elements per item when multiple authors exist. **This is non-conformant with v0.1.0**, which defines a singular author-per-item model.

Current plugin behavior: the RSS2 and Atom output loops emit one `byline:role` element after each `byline:author ref` element. In practice this means each author gets its own role, but the binding is positional, not structural — nothing in the XML formally groups an author ref with the role that follows it. A naive parser could misinterpret the sequence, and the spec does not define this pattern.

Options for resolving this:

1. **Emit only the primary author** — fully spec-compliant, simple, but loses co-author data that WordPress publishers expect to see in their feeds.
2. **Continue emitting multiple authors and document the divergence** — non-conformant. The positional author-then-role pattern is fragile and not sanctioned by the spec. A reader that expects one author can use the first, but the role binding is ambiguous.
3. **Propose a spec revision for multi-author-per-item** — e.g., wrapping each author+role in a container element, or a `role` attribute on `byline:author`. The cleanest evolution path would preserve the current singular form for simple cases and add an explicit structural form only for multi-author items. This is the right long-term path if multi-author matters to the spec's audience.

The plugin currently follows option 2. This should be explicitly documented as a known spec divergence and discussed with the spec author before a stable release.

### Role vocabulary observation

The spec defines: `creator`, `editor`, `guest`, `staff`, `founder`, `contributor`, `bot`. In WordPress, the mapping from capabilities to these values is straightforward for individual authors but requires interpretation for organizational bylines. A post attributed to "Staff Report" or "The Editorial Board" doesn't map cleanly to any individual role. The spec may want to document how organizational attribution interacts with the role vocabulary, or clarify that `byline:org` handles this case and per-item `byline:author` should only reference `byline:person` entries.

---

## 2. JSON Feed mapping: spec model vs. plugin model

### What the spec defines

The Byline spec v0.1.0 already provides a JSON Feed mapping. The `corporate-blog.json` example shows:

- A feed-level `_byline` object containing `version`, `organizations[]`, and `contributors[]`
- Per-item `_byline` object containing `author` (a **scalar string** referencing a contributor id), `role`, `perspective`, and `affiliation`

This mirrors the XML structure: contributors are defined once at feed level, items reference them by id.

### Where the plugin diverges

Our plugin uses a **different JSON structure** that nests `_byline` inside JSON Feed's standard `authors[]` entries instead of using the spec's top-level `_byline.contributors` model:

**Spec model** (from `corporate-blog.json`):
```json
{
  "_byline": {
    "contributors": [{ "id": "jdoe", "name": "Jane Doe", ... }]
  },
  "items": [{
    "_byline": { "author": "jdoe", "role": "staff" }
  }]
}
```

**Plugin model** (what `feed-json.php` produces):
```json
{
  "authors": [{
    "name": "Jane Doe",
    "url": "https://...",
    "_byline": { "id": "jdoe", "role": "staff", ... }
  }],
  "items": [{
    "authors": [{
      "name": "Jane Doe",
      "_byline": { "id": "jdoe", "role": "creator" }
    }]
  }]
}
```

### Why the plugin diverges

The plugin model nests Byline data inside JSON Feed's native `authors` array for progressive enhancement. Any JSON Feed reader can display author names, URLs, and avatars without understanding `_byline`. The spec model puts all author identity inside a `_byline.contributors` block that non-Byline readers would ignore entirely.

This is a deliberate trade-off: the plugin prioritizes backward compatibility with existing JSON Feed readers over strict spec conformance. **Current plugin JSON output is non-conformant with the spec's `_byline.contributors[]` feed-level model and scalar `_byline.author` item-level reference pattern shown in `corporate-blog.json`.** The two models are structurally incompatible — a reader built for the spec's model won't find authors where the plugin puts them, and vice versa.

### Recommendation

This divergence should be resolved before either model sees significant adoption. Options:

1. **Align the plugin with the spec's model.** Accept that non-Byline JSON Feed readers won't see contributor data beyond what's in standard fields.
2. **Propose the plugin's model to the spec.** Argue that nesting `_byline` on `authors[]` entries is more JSON-idiomatic and offers better progressive enhancement.
3. **Support both.** Emit `_byline.contributors` for spec compliance and also populate `authors[]._byline` for progressive enhancement. Redundant but maximally compatible.

This needs to be settled with the spec author before shipping a 1.0 plugin.

---

## 3. Terminology drift: organization, publication, and publisher

The spec defines `organization` as a structured entity type, but also uses `publication` and `publisher` in prose without clearly distinguishing them. This creates avoidable ambiguity for implementors.

These terms are not interchangeable:

- `organization` is an entity type in the model
- `publication` reads as the work, feed, or publishing output
- `publisher` reads as a relationship/property term, not an entity type

In the current draft, prose sometimes appears to blur these categories. That may not break a parser, but it can create implementation drift: one implementor may treat "publication" as a synonym for organization, while another may infer that it is a separate concept the spec intends to formalize later.

This does **not** change the separate role-per-author ambiguity discussed above. That issue is structural: the plugin emits repeated `byline:author ref` and `byline:role` siblings without formal grouping. The terminology drift here is different — a prose/modeling ambiguity, not an item-structure ambiguity.

Recommendation: add a short terminology note near the top of the spec clarifying that `organization` is the defined entity type, while `publication` and `publisher` are prose terms unless formally defined otherwise.

---

## 4. The perspective/velocity connection

### What Current does today

Terry Godier's Current RSS reader introduces a concept of *velocity* — how quickly content ages in the reader's river view. Each source is assigned one of five speeds: Breaking, News, Article, Essay, or Tutorial. Breaking news fades after three hours; tutorials linger for a week.

Currently, velocity is assigned by the *subscriber* per *source*. You tell Current "Daring Fireball is an Article-speed source" and every post from that feed ages at the Article rate. This is a blunt instrument. A single source might publish breaking news, long-form essays, and tutorials. The New York Times's RSS feed mixes all three. A personal blog alternates between quick link posts and ten-thousand-word essays. Source-level velocity is a compromise for the absence of item-level signals.

### What perspective enables

Byline's `perspective` element is the publisher-side signal that makes item-level velocity possible. Consider the mapping:

| Byline perspective | Natural velocity | Content lifecycle |
| --- | --- | --- |
| `reporting` | Breaking / News | Burns bright, fades fast |
| `analysis` | Article | Medium persistence |
| `personal` | Essay | Lingers |
| `tutorial` | Tutorial / Evergreen | Stays longest |
| `review` | Article | Medium persistence |
| `announcement` | News | Time-bound by nature |
| `official` | News | Institutional, time-stamped |
| `sponsored` | Article | Reader may want to deprioritize |
| `satire` | Essay | Timeless if good, dated if topical |
| `curation` | News / Article | Depends on content |
| `fiction` | Essay | Persistent |
| `interview` | Article / Essay | Medium to long persistence |

A Byline-aware reader like Current could auto-assign velocity per *item* rather than per *source*, using the publisher's perspective signal as a hint. A post from the New York Times with `perspective="reporting"` fades after three hours; a post from the same feed with `perspective="analysis"` lingers for a day. The reader could still allow subscriber overrides, but the default would be dramatically better.

### Why this should be in the spec

The spec describes perspective as addressing "content collapse" — helping readers distinguish between different content types in a unified stream. But it doesn't describe *how* a reader should use this information. The mechanism is left entirely to the implementor.

For reader developers evaluating whether to support Byline, the single most compelling argument is: "here's what you can *do* with this data that you can't do without it." The perspective-to-velocity mapping is that argument. A reader that parses `byline:perspective` can make better display, sorting, and aging decisions per item without requiring manual subscriber configuration.

The spec should include an "Implementor guidance for readers" section that documents this pattern — not as a requirement, but as a suggested application. Something like:

> **Suggested reader behavior.** The `perspective` element signals the publisher's intent for how the content should be contextualized. Readers may use this signal to inform display decisions such as content aging, visual weight, grouping, and filtering. For example, a reader with time-based content display might use `reporting` and `announcement` perspectives as indicators of ephemeral content, while `tutorial` and `fiction` perspectives indicate persistent content.

### The perspective vocabulary: observations from implementation

After building the editorial UI (a `<SelectControl>` dropdown in the WordPress block editor sidebar), several questions arose:

**Missing values.** There's no perspective for podcast episodes, photo essays, or link roundups. Is a link roundup `curation`? Is a podcast episode `interview` even when it's a monologue? Is a photo essay `personal`? These edge cases suggest the vocabulary may benefit from being extensible — either through custom values or a documented convention for vendor extensions.

**Overlap between values.** `personal` and `analysis` overlap significantly. Most opinion columns are both personal *and* analytical. When an editor can only pick one, they'll pick whichever their publication's style guide says, which means different publications will classify the same kind of content differently. This is acceptable — perspective is editorial judgment, not objective classification — but the spec should acknowledge this and suggest that readers treat perspective as a hint rather than a category.

**The site-level default.** A tutorial blog writes tutorials. A satire publication writes satire. Making every editor set `perspective="tutorial"` on every post is friction that will prevent adoption. The spec should support a channel-level default perspective that items inherit unless overridden:

```xml
<channel>
  <byline:perspective>tutorial</byline:perspective>
  <!-- items inherit this unless they specify their own -->
</channel>
```

Or in JSON Feed:

```json
{
  "_byline": {
    "perspective": "tutorial"
  },
  "items": [
    {
      "title": "How to Configure Nginx",
      "_byline": {}
    },
    {
      "title": "Why I Switched to Caddy",
      "_byline": {
        "perspective": "personal"
      }
    }
  ]
}
```

In WordPress, we implement this via a filter that lets sites set a default:

```php
add_filter( 'byline_feed_perspective', function ( $perspective, $post ) {
    // If no explicit perspective is set, default to tutorial.
    if ( empty( $perspective ) ) {
        return 'tutorial';
    }
    return $perspective;
}, 10, 2 );
```

This works today without any spec change, but documenting the inheritance pattern in the spec would encourage consistency across implementations.

---

## 5. The contributor pre-pass pattern

### Why this matters

The Byline spec defines a channel-level `<byline:contributors>` block that contains `<byline:person>` elements for all authors referenced in the feed. Per-item `<byline:author ref>` elements then reference these person definitions by id.

This ref/id pattern is efficient — author identity data is defined once and referenced many times — but it requires the feed generator to know all authors *before* emitting the first byte of feed output. In a streaming context (which is how WordPress generates feeds — hooks fire inside a template loop), this means a pre-pass over the entire post set to collect unique authors.

### Implementation pattern

```php
function output_contributors(): void {
    global $wp_query;

    if ( empty( $wp_query->posts ) ) {
        return;
    }

    $seen    = array();
    $persons = array();

    foreach ( $wp_query->posts as $post ) {
        $authors = byline_feed_get_authors( $post );

        foreach ( $authors as $author ) {
            if ( isset( $seen[ $author->id ] ) ) {
                continue;
            }
            $seen[ $author->id ] = true;
            $persons[]           = $author;
        }
    }

    if ( empty( $persons ) ) {
        return;
    }

    // Emit <byline:contributors> with all unique persons.
    // ...
}
```

This is called from the `rss2_head` / `atom_head` hook, which fires once before the item loop. The post set (`$wp_query->posts`) is already loaded into memory by WordPress at this point, so the pre-pass is iterating an in-memory array, not making additional database queries. But it *is* calling `byline_feed_get_authors()` for every post twice — once in the pre-pass and once in the per-item output. Caching the results (keyed by post ID) eliminates the redundancy.

### Recommendation for the spec

Add an "Implementor's note" to the contributors section of the spec:

> **Implementor's note.** The contributors block requires feed generators to resolve all author references before beginning feed output. In streaming or template-based feed generators, this typically means a pre-pass over the item set to collect unique authors, followed by the channel-level contributors output, followed by per-item output that references the pre-collected authors. Implementations should cache the author resolution results from the pre-pass to avoid redundant lookups during per-item output.

This is the kind of practical guidance that saves every implementor from independently discovering the same pattern.

---

## 6. Additional observations

### Atom is the reference format for multi-author

RSS2 relies entirely on the Byline namespace for structured authorship. Atom natively supports multiple `<author>` and `<contributor>` elements per entry, each with `<name>`, `<uri>`, and `<email>`. This means an Atom feed with Byline extensions has *two* layers of multi-author data: native Atom Person Constructs (which any Atom parser understands) plus Byline extensions (which provide the richer identity). The spec should acknowledge this and recommend Atom as the reference format for testing multi-author Byline support.

### Backward compatibility is doing the work

The spec's principle that "Byline is additive — always include standard elements for maximum compatibility" is correct and important. In our implementation, the standard `<author>`, `<dc:creator>`, and Atom `<author>` elements are completely untouched. Byline elements are injected alongside them. Feed readers that don't understand Byline see exactly the same feed they always did. This means the cost of Byline adoption for publishers is zero (no existing reader behavior changes) and the benefit ramps up as readers add support.

### The `byline:theme` element should be explicitly optional-optional

Theme colors are the kind of feature that publishers will enthusiastically misuse. A publisher who sets their theme to `#FF0000` text on `#00FF00` background is doing violence to every reader that honors the hint. The spec calls theme "hint-only" but should go further: recommend that readers treat theme colors as accent tints with aggressive contrast validation, never as literal foreground/background overrides. Most accessibility-conscious reader developers will ignore theme entirely unless this guidance is clear.

### Feed-level rights metadata

Our WP-06 work package (content rights and AI consent) produces per-item consent signals. The most natural place for this in a Byline-extended feed is a `byline:rights` or `byline:consent` element per item. The spec doesn't currently address rights. If Byline intends to address content collapse holistically — not just "what kind of content is this" but "what may be done with this content" — a rights extension would be a natural addition. We're currently using `<cc:license>` from the Creative Commons namespace alongside Byline elements, but a unified Byline rights element would be cleaner.

---

## Summary of proposed spec changes

| Issue | Proposal | Priority |
| --- | --- | --- |
| Multi-author-per-item is non-conformant | Document divergence; propose spec revision if demand exists | Medium — plugin currently ships non-conformant output for multi-author posts |
| JSON Feed mapping divergence | Align plugin with spec model, or propose alternative to spec author | High — two incompatible JSON models before any adoption is the worst outcome |
| Perspective has no reader guidance | Add "suggested reader behavior" section | Medium — drives reader adoption |
| No channel-level perspective default | Document inheritance pattern | Medium — reduces editorial friction |
| Contributor pre-pass not documented | Add implementor's note | Medium — saves implementor time |
| Role vocabulary gaps (org-as-author) | Clarify org/person interaction | Low — edge case, deferrable |
| Theme misuse risk | Strengthen hint-only guidance | Low — cosmetic concern |
| No rights/consent metadata | Consider rights extension | Future — dependent on spec scope |
