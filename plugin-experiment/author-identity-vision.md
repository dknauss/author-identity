# Author Identity, Content Provenance, and Distribution Control

## Elevator pitch

Your byline should travel with your work — your name, your credentials, your perspective, your rights — everywhere it goes. Feeds, search engines, the fediverse, AI systems. One plugin, one source of truth, every output channel.

Right now, when your article leaves your site, it gets stripped down to a name and a URL. Your analysis looks identical to a press release in a feed reader. Your co-author disappears. Your "don't train on this" preference is invisible. Your fediverse identity is disconnected from your publishing identity.

We fix that. A WordPress plugin that takes the author data you already have — from Co-Authors Plus, PublishPress Authors, or plain WordPress — and pushes structured identity into RSS feeds (via the Byline spec), HTML schema (for search and AI), and fediverse metadata (via `fediverse:creator`). With content perspective labels so readers know if they're reading reporting or opinion, and per-author rights signals so your consent preferences are machine-readable.

Forty thousand multi-author WordPress sites. Zero structured identity in their feeds today. We go first.

## Purpose

This document extends the Byline feed adoption strategy into broader territory: how structured author identity in WordPress intersects with ActivityPub federation, LLM discoverability, technical SEO, and intellectual property protection. It is a vision document, not an implementation spec. It identifies convergences, tensions, and practical components that could be built incrementally on top of the Byline feed plugin described in `byline-adoption-strategy.md`.

## Target communities

### Professional journalists and newsrooms

The Byline spec's `perspective` element solves a real editorial problem: in a feed reader, an investigative analysis and a corporate press release are structurally identical. Newsrooms already care about this — ProPublica, The Markup, CalMatters, and similar WordPress-based outlets invest significant effort in distinguishing their journalism from the surrounding information environment.

The `affiliation` element also maps directly to existing editorial practice. FTC disclosure requirements for sponsored content and newsroom style guide conflict-of-interest rules are already standard. That disclosure currently lives in body text and gets lost in syndication. Structured feed metadata makes it machine-readable and displayable by reader applications — a badge that says "this author is employed by the company they're writing about."

For these organizations, the pitch is not "add metadata to your feed." It is: "your editorial standards already require this information — we make it travel with the content instead of getting stripped at syndication."

### Independent writers and bloggers

The IndieWeb community already values feed-first publishing, `rel="me"` mutual link verification, POSSE (Publish on your Own Site, Syndicate Elsewhere), `/now` pages, and `/uses` pages. Byline references these conventions natively. The audience is smaller but highly engaged, technically capable, and influential in setting norms for the open web.

For these writers, the pitch is: "your identity and context should follow your writing wherever it goes — not just on your own site."

### Publishers concerned about AI and distribution

This is the fastest-growing constituency. Writers and publishers want to be found and credited by AI systems but do not want their work harvested as training data without compensation or consent. These goals are in tension, and any tooling that pretends otherwise is not credible. What structured metadata can do is make the tension *manageable* by giving publishers granular, machine-readable control over identity, attribution, and rights signaling.

## ActivityPub convergence and tension

### What converges

The ActivityPub plugin for WordPress (Automattic project, maintained by Matthias Pfefferle) turns WordPress posts into ActivityPub objects that federate across Mastodon and the fediverse. An ActivityPub `Article` object has an `attributedTo` field pointing to an Actor — the author.

Byline's `byline:person` and ActivityPub's Actor concept solve the same problem — portable, verifiable author identity — in different distribution contexts. Byline addresses syndication feeds (RSS, Atom, JSON Feed). ActivityPub addresses federation (Mastodon, Pleroma, Misskey, etc.).

A plugin that populates both from the same WordPress author data gives writers a consistent identity across both channels. Their Mastodon followers and their RSS subscribers see the same structured identity, verified by the same `rel="me"` mutual links.

### What diverges

**Verification models.** ActivityPub uses cryptographic signatures — the origin server signs every object with its private key, and receiving servers verify. Byline uses IndieWeb-style `rel="me"` mutual linking, which is social proof rather than cryptographic proof. These are not incompatible but represent different trust levels. A sophisticated implementation could use ActivityPub's cryptographic identity as the strong verification layer and Byline's profile links as the human-readable discovery layer.

**Content distribution.** ActivityPub federates full content — the `content` field of the Activity object typically includes the post body. RSS can include either full content or summaries. Publishers who want to control distribution may prefer to syndicate excerpts with full Byline identity via RSS (maximum discoverability, minimum content exposure) while gating full content behind their own site. ActivityPub complicates this because Mastodon expects full content. This tension is not something a plugin can fully resolve, but it can offer the choice.

**Multi-author representation.** This is the most active area of development at the intersection of WordPress and the fediverse, with several concrete threads converging:

**Mastodon's `fediverse:creator` meta tag (July 2024).** Mastodon introduced a new OpenGraph-style meta tag for author attribution on shared links: `<meta name="fediverse:creator" content="@user@instance" />`. When a link is shared on Mastodon, the author byline becomes clickable, opening the author's fediverse profile directly in the app. This was explicitly designed for journalism — the launch partners were The Verge, MacStories, and MacRumors. The tag works with any fediverse account (Mastodon, Flipboard, Threads, WordPress with ActivityPub plugin, PeerTube, Pixelfed).

Source: Eugen Rochko, "Highlighting journalism on Mastodon," blog.joinmastodon.org, July 2, 2024.

**Multi-author limitation acknowledged.** The same blog post states: "If multiple tags are present on the page, the first one will be displayed, but we may add support for showing multiple authors in the future. We intend to propose a specification draft for other ActivityPub platforms in the coming weeks." As of the June 2024 engineering update, Mastodon introduced an `authors` attribute in the REST API for link previews that "cannot contain more than one author on Mastodon, but this might change."

Sources: blog.joinmastodon.org/2024/07/highlighting-journalism-on-mastodon/ and blog.joinmastodon.org/2024/07/trunk-tidbits-june-2024/ (PR #30846).

**WordPress ActivityPub plugin — post author/object actor synchronization (closed).** Issue #2353 on the Automattic/wordpress-activitypub repo identified the fundamental problem: WordPress's `post_author` and the ActivityPub object's `actor`/`attributedTo` can diverge, especially with multi-author plugins like Co-Authors Plus. This led to Discussion #2358, a draft pre-FEP (Fediverse Enhancement Proposal) titled "Reassigning Actor and CoAuthor Representation for Federated CMS," which proposed new ActivityPub activity types (`Reattribute`, `Transfer`) for author reassignment and co-authorship.

**The pre-FEP proposal (SocialHub thread, October 2025).** The proposal by jiwoon uses an English grammar analogy to map ActivityPub activities to sentence structures. The core argument: ActivityPub currently handles "subject-verb-object" actions well (3rd form: "Alice Created Note") but has no native vocabulary for expressing ownership or authorship change, which requires a ditransitive or complement structure. Author reassignment — "Admin reattributes Post to Mogu" — is modeled as a 5th-form sentence (S+V+O+O.C) where the activity changes an ontological property of the object (who it belongs to), not just its location. The proposal distinguishes between 4th-form transfer ("give something to someone," like `Move`) and 5th-form reattribution ("make something become a new state"), arguing that changing an author is identity transformation, not spatial relocation. Concrete new activity types were proposed: `Reattribute` (with `result.attributedTo` pointing to the new author) and `Transfer` (for media ownership changes via the `mediaUpload` endpoint).

The proposal also references Ghost's ActivityPub implementation (TryGhost/ActivityPub) as facing the same multi-author gap, and notes that in a CMS context, changing `post_author` is a routine editorial operation that ActivityPub's assumption of immutable authorship cannot express.

**The critical response reveals a deeper architectural issue.** The SocialHub comment thread surfaced a fundamental objection from silverpill and trwnh — two of the most technically rigorous voices in fediverse protocol discussions:

silverpill argued that co-authorship and author reassignment are separate problems. On co-authorship: `attributedTo` should always be a single actor because it carries special meaning for ownership and authorization (per FEP-fe34, the origin-based security model that supersedes FEP-c7d3). Co-authors should use a different property entirely to avoid conflating authorship with ownership. On reassignment: changing an object's author is technically similar to migrating a post between servers, and FEP-1580 (by jonny) already addresses object portability.

trwnh's response went deeper into the fundamental problem: **`attributedTo` bundles too many concerns that should be separate — it is used not only for authorship, but also ownership, with no distinction between the two.** In the current fediverse, `attributedTo` simultaneously means "who created this" (authorship), "who controls this" (ownership/authorization — only the `attributedTo` actor can Update or Delete the object), and implicitly "whose outbox this came from" (provenance). These are three different relationships that happen to be the same actor in simple cases but diverge in multi-author and CMS contexts.

trwnh identified several possible approaches to untangling this: use a different property for authorship (like `dc:author` or `dcterms:author`) while keeping `attributedTo` for ownership; split ownership into a separate property; derive ownership from the object's `id` (the web origin/authority component); or infer ownership from container membership. But all approaches require peers to share the same understanding, and the concept of "ownership" itself is fuzzy — the Security Vocabulary has already deprecated "owner" in favor of "controller."

trwnh also flagged that some platforms don't care about activities at all (they only care about objects), so they wouldn't know how to process an Update whose target is an Activity. Other platforms treat activities as an immutable event stream. The fundamental question that needs answering first is not "what activity type represents author change" but "who should have control, and from where does authority derive?"

**Why the pre-FEP was rejected (and why it matters for our project).** The proposal was rejected not because the problem is unreal but because it tried to solve a downstream symptom (no activity type for author change) without resolving the upstream cause (the conflation of authorship, ownership, and control in `attributedTo`). The fediverse community's position is: before defining vocabulary for assignments and transfers, first reach agreement on the more fundamental question of how control and identity work in a distributed system.

**Implications for the Byline identity plugin:**

1. **Do not assume `attributedTo` arrays are the long-term solution for multi-author.** The fediverse's direction may be to keep `attributedTo` as a single-actor ownership field and express co-authorship via a separate property. Our adapter should be designed to output authorship data to *whatever property emerges* — `attributedTo` today (since it works with Plume's proven pattern), a future `dc:author` or custom property if the specs evolve.

2. **The authorship/ownership distinction is exactly what Byline solves in feeds.** The Byline spec's `byline:role` element (creator, editor, guest, staff) and `byline:author ref` are *precisely* the kind of separate authorship property that the fediverse is groping toward for `attributedTo`. A Byline identity plugin that demonstrates clean separation of authorship (Byline elements) from ownership (whatever the hosting platform uses) could inform the ActivityPub discussion.

3. **The underlying problem (post_author/actor divergence) remains unsolved.** It's worth monitoring whether the WordPress ActivityPub plugin team addresses it through internal architecture changes or new filter hooks.

4. **Feed-level identity is independent of this entire debate.** RSS and Atom feeds don't have the authorship/ownership conflation problem. Byline elements can express multi-author attribution cleanly because feeds are read-only — there's no "who can modify this" concern. This makes feeds the safest ground for shipping multi-author identity first.

References: github.com/Automattic/wordpress-activitypub/issues/2353 (closed), github.com/Automattic/wordpress-activitypub/discussions/2358, socialhub.activitypub.rocks/t/pre-fep-reassigning-actor-and-coauthor-representation-for-federated-cms/8172, FEP-fe34 (origin-based security model), FEP-1580 (object portability).

**Plume: prior art for multi-author `attributedTo` in production.** Plume (joinplu.me) is a federated blogging engine written in Rust that has been shipping `attributedTo` as a list in production since its early versions. Plume's federation documentation states: "`object.attributedTo` is a list containing the ID of the authors and of the blog in which this article have been published. If no blog ID is specified, the article will be rejected."

Source-level inspection of the codebase (plume-models/src/posts.rs) reveals a fully realized multi-author data model:

**Database layer.** A `post_authors` join table with `post_id` and `author_id` columns implements a proper many-to-many relationship between posts and users. A separate `blog_authors` table with `blog_id`, `author_id`, and `is_owner` handles blog membership. These are distinct relationships — who can write for this blog vs. who authored this specific post.

**Sending side** (posts.rs lines 362-368). When federating an Article, the code calls `get_authors()` which queries the `post_authors` join table and returns a `Vec<User>`. It collects their AP URLs into a Vec, then pushes the blog's AP URL onto the end, and calls `set_many_attributed_tos(authors)`. The test fixtures confirm the output: `"attributedTo": ["https://plu.me/@/admin/", "https://plu.me/~/BlogName/"]`. If multiple authors were assigned to a post via the join table, the output would naturally be `["https://plu.me/@/author1/", "https://plu.me/@/author2/", "https://plu.me/~/BlogName/"]`.

**Receiving side** (posts.rs lines 630-653). When parsing an incoming Article, the code iterates the `attributedTo` list using a fold. For each URL, it first tries `User::from_id()` — if that succeeds, the entry is an author and gets pushed into an authors vec. If that fails, it tries `Blog::from_id()` — if that succeeds, it's the blog. This design doesn't depend on ordering or type annotations; it resolves each URL dynamically. Multiple Users all accumulate in the authors vec.

**The significance:** Plume's architecture is designed from the start for multi-author `attributedTo`. The join table, the `Vec<User>` return type, and the receiving-side fold that accumulates multiple authors are all in place. The Lemmy team wrote specific handling code for Plume's `attributedTo` format, and the SocialHub forum documents cross-platform interop with this pattern.

**What is NOT implemented:** The collaborative writing UI — the ability for multiple humans to be assigned to a single post through the editorial interface — is listed in the README as planned for 1.0 but not shipped. The project appears to be in low-activity maintenance mode (156 open issues, GitHub repo is a mirror of their Gitea instance). Plume proves the federation architecture and data model for multi-author content, but the editorial workflow that would populate the `post_authors` table with multiple entries per post has not been built.

**Lesson for our project:** Plume demonstrates that multi-author `attributedTo` is not theoretical — the database schema, the serialization code, the parsing code, and the cross-platform interop all exist. WordPress multi-author plugins already have the editorial UI that Plume lacks (assigning multiple authors to a post). What WordPress lacks is the federation layer that Plume has. A Byline identity plugin bridges this gap by taking WordPress's multi-author data and outputting it in the same patterns that Plume has already proven work across the fediverse.

Sources: docs.joinplu.me/federation/ (federation documentation), plume-models/src/posts.rs (source), plume-models/src/post_authors.rs (join table), socialhub.activitypub.rocks/t/differences-in-group-federation-between-projects/2472, socialhub.activitypub.rocks/t/how-to-implement-activitypub-for-a-blog-that-has-multiple-authors/2673.

**Ghost's ActivityPub implementation.** Ghost (TryGhost/ActivityPub) is also grappling with multi-author federation — the pre-FEP discussion explicitly references Ghost's implementation alongside WordPress. Ghost's forum has active threads on how multi-author content appears in the fediverse, with the current behavior attributing all content to a single site-level account.

Source: forum.ghost.org/t/multiple-authors-shared-to-the-fediverse-what-does-that-look-like/59502.

**Current limitations.** The default Mastodon web UI still displays a single author for interaction purposes (replying, liking). The `fediverse:creator` tag currently only shows the first tag when multiple are present. The pre-FEP for co-author representation is in draft status and has not been formally proposed. PeerTube and other platforms that handle multi-contributor content use workarounds — attributing to a single primary account and mentioning others in body or metadata.

### Practical interop

The `fediverse:creator` meta tag is the most immediate and concrete integration point for a WordPress author identity plugin. It requires no ActivityPub protocol changes — it's a standard HTML meta tag that WordPress can output in `wp_head` using the same normalized author data that feeds the Byline output.

Concrete steps:

1. **Output `fediverse:creator` meta tags** from normalized author data. For each attributed author who has a fediverse handle (stored as user meta), output `<meta name="fediverse:creator" content="@handle@instance" />`. This works today with Mastodon's existing support, even though only the first author is displayed.
2. **Populate the Mastodon REST API `authors` attribute** — this happens automatically when Mastodon fetches and parses the page's OpenGraph tags, so no additional work is needed beyond outputting the meta tags.
3. **Work within existing primitives.** The pre-FEP for new activity types was rejected (#2358), confirming that multi-author federation will be solved with existing AP primitives and HTML mechanisms, not protocol extensions. A plugin that demonstrates effective multi-author attribution using `fediverse:creator` tags, `attributedTo` arrays, and `tag` mentions builds practical evidence for how the ecosystem should handle this — which is more persuasive than a spec proposal.
4. **Coordinate with the ActivityPub plugin team.** The post_author/object_actor sync issue (#2353, closed) identified the problem but didn't resolve it. The actor management proposal (Discussion #547) suggests architectural changes are planned. If the ActivityPub plugin exposes filters for customizing `attributedTo`, the Byline identity plugin could use those filters to inject multi-author data without protocol changes.
5. **Map Byline `role` values to fediverse metadata.** A `guest` author vs. a `staff` author carries editorial meaning that could inform how platforms display the attribution. This is forward-looking — no current platform uses this — but establishing the convention early influences the spec.

## Untangling attribution, control, provenance, and rights

The SocialHub discussion of the pre-FEP proposal surfaced trwnh's observation that `attributedTo` bundles too many concerns. But even that critique uses "ownership" as if it's a single thing, and it isn't. What's actually tangled up in `attributedTo` is at least four distinct relationships, and the word "ownership" obscures the differences between them.

### Attribution

Who created this, in the credit and citation sense. This is what a byline is. It answers "whose intellectual work is this?" and it can be multiple people, it can be pseudonymous, and it doesn't inherently carry any control rights. A CC-BY license requires preserving attribution but specifically does not preserve ownership — the whole point is that the work can be copied, modified, and redistributed. Attribution survives relicensing. It is the most durable relationship between a person and a work.

Attribution also doesn't require authentication. You can credit a pseudonym or "Anonymous" and the attribution is perfectly valid. A pseudonymous blog post attributed to "Satoshi Nakamoto" is correctly attributed even though nobody can authenticate who that is.

### Control

Who can modify or delete this object in the technical system. In ActivityPub, this is enforced by the "same actor" policy: only the actor in `attributedTo` can send Update or Delete activities for the object. This is an access control concern, not an authorship concern. On a WordPress site, an editor can modify a post they didn't write. In a CMS, control is role-based, not authorship-based. The fediverse conflates these because most fediverse software is single-user-per-account, where the person who writes a post is always the person who controls it.

Control requires authentication. You need to prove you are who you claim to be before the system lets you modify or delete something.

### Provenance

Where did this object come from, whose server delivered it, whose cryptographic keys signed it. This is what FEP-fe34 is really about with "origin-based security." It's a server trust question, not an authorship question. When Mastodon receives a federated Article, it trusts it because the delivering server's signature matches the `attributedTo` actor's public key. That's infrastructure, not editorial metadata.

### Intellectual property

Who holds legal rights to the work, and under what terms. This is where "ownership" as a concept gets slippery, because the answer depends entirely on the license model, the cultural framework, and the specific relationships between co-authors.

Under default copyright law (in most jurisdictions), the creator holds copyright. But work-for-hire doctrine means the employer often holds copyright for work created in the scope of employment — a staff reporter at a newsroom doesn't own their articles; the publication does. A guest contributor might retain copyright while granting a license to publish. A CC-BY-SA work has been released such that "ownership" in the proprietary sense is deliberately relinquished while attribution is preserved. An anonymous or pseudonymous author has full copyright protection under the Berne Convention — anonymity doesn't waive rights, it just makes enforcement harder.

**The co-author ownership question.** When multiple authors co-create a work, who "owns" it? The answer varies by jurisdiction and agreement. In the US, a "joint work" under copyright law gives each co-author an undivided interest in the whole — any co-author can license it non-exclusively without the others' consent, but must share proceeds. In the EU, the rules differ by member state. In academic publishing, the "principal investigator" model establishes a hierarchy of contribution that doesn't exist in copyright law but carries enormous professional weight. In journalism, the publication typically holds copyright regardless of how many reporters contributed.

Creative Commons licenses make this tension explicit and deliberate. A CC-BY license requires preserving *attribution* — every co-author must be credited — but does not preserve *ownership* of subsequent derivatives. The license specifically severs the link between "who created this" and "who controls what happens to it next." CC-BY-SA adds the requirement that derivatives carry the same license, but still doesn't give the original authors control over derivative works — only the right to be named. This is attribution surviving the death of ownership, by design.

So the fediverse discussion's casual use of "ownership" to describe what `attributedTo` does is misleading. `attributedTo` in the CC-licensed web is often pointing to someone who has *no ownership rights over the object at all* — only the right to be credited as its creator. The property name itself — "attributed to" — is more accurate than the community's interpretation of it.

**Organizations as authors.** Can a blog, newsroom, or group be cited as an author? In practice, yes — "Staff Report," "AP News," "The Editorial Board" are all common bylines. Plume's model puts the blog itself into `attributedTo` alongside the person authors, treating the blog as a Group actor and the authors as Person actors. In Lemmy's group federation model, `attributedTo` on a Group points to the group's moderators. In journalism, wire service articles are routinely attributed to the organization, not individual reporters.

The Byline spec handles this through `byline:org` (organizational metadata) alongside `byline:person` (individual authors). The plugin should support: individual attribution only ("Jane Doe"), organizational attribution only ("Staff Report"), and mixed attribution ("Jane Doe, The Daily Example") — matching how newsrooms actually use bylines in practice.

**"Ownership" is culturally contingent.** Indigenous knowledge systems, collective authorship traditions, and oral cultures have concepts of intellectual stewardship that don't map cleanly to Western IP law's individual-ownership model. The word "ownership" smuggles in assumptions about commodifiable intellectual property that don't apply universally. A traditional story "belongs to" a community in a sense that has nothing to do with copyright or access control and everything to do with responsibility and relationship. The fediverse discussion, rooted in Western tech culture, doesn't grapple with this — but a plugin that serves diverse WordPress publishers should at least not foreclose on non-Western authorship models by hard-coding assumptions about individual ownership.

**Authentication and enforcement are symmetrical.** IP enforcement requires legal identity — you can't file a DMCA takedown as a pseudonym (in practice), and you can't be sued for defamation if you can't be identified. But IP *existence* doesn't require identification. Copyright attaches to the work at the moment of creation regardless of whether the author is identified. The key insight is that authentication serves *both sides* of enforcement: it's needed to assert your rights (takedown notices, licensing claims) *and* to have your rights curtailed (court orders, liability for harmful content). The anonymity spectrum isn't just about protecting authors from consequences — it's also about authors choosing how much enforcement power they want to wield.

### What `attributedTo` actually conflates

So when trwnh says `attributedTo` conflates authorship and ownership, it's actually worse than stated. In a CMS context, `attributedTo` simultaneously means:

- "Who created this" (attribution) — the staff reporter
- "Who controls this" (access control) — the CMS admin or any user with `edit_others_posts` capability
- "Whose server delivered this" (provenance) — the publication's server
- "Who holds legal rights" (IP) — the media corporation, or the author, depending on the employment relationship and license

These are four different actors in a newsroom. They happen to be the same person on a single-user Mastodon account, which is why the conflation went unnoticed for so long.

### The newsroom workflow problem

This connects directly to a practical question raised by @django in the broader fediverse CMS discussion: do authors want to be tagged and have to manage replies, or does that fall to the media organization, with the author simply sharing, boosting, or announcing their recent post created under the organization's account?

The answer in most newsroom contexts is clear: the organization manages replies, not the individual author. When a publication posts an article on the fediverse, replies go to the publication's account, not to the reporter's personal account. The reporter might boost the article from their personal account, but they don't want their personal mentions flooded with responses to institutional reporting.

This maps to a clean separation that the plugin should express:

**Attribution (Byline layer).** "This article was written by Jane Doe, staff reporter." This is the `byline:author` and `fediverse:creator` output — it credits Jane and lets readers discover her identity, her other work, her credentials. It is a read-only discoverability signal, not a federation-level mention.

**Control and interaction (ActivityPub layer).** The federated Article object's `actor` is the publication's account (@thenewsroom@example.com), which receives replies, manages threads, and handles moderation. The publication decides editorial policy about responses.

**Amplification (social layer).** Jane boosts or announces the article from her personal fediverse account (@janedoe@mastodon.social), driving her followers to the institutional post. This is a social action, not an editorial one.

The Byline identity plugin can express all three layers cleanly: Byline elements for attribution, `fediverse:creator` for linking to the author's discoverable identity, and the existing ActivityPub plugin handling the control/interaction layer through the publication's account. The plugin doesn't need to solve the "who gets replies" question — it just needs to make sure its author identity output doesn't *create* the reply problem by tagging authors in ways that flood their mentions.

### Implications for the identity spectrum

The plugin architecture should accommodate a full spectrum of identity without forcing a single model:

**Fully identified named authors** with verified profile links, real-name JSON-LD schema, and `fediverse:creator` tags pointing to authenticated accounts. This is the E-E-A-T optimized case, suitable for journalists and public-facing professionals.

**Pseudonymous authors** with a consistent pen name, a fediverse handle that may not map to a legal identity, and Byline elements that carry the pseudonym's reputation. A pseudonymous author's `noai` declaration has the same legal standing as a named author's — copyright attaches to the work regardless.

**Anonymous contributors** where attribution is "Anonymous," "Staff Report," or a collective byline. Byline elements still function — a feed reader can display "Staff Report, The Daily Example, reporting" — and control is handled entirely by the organization.

**Bots and AI** where the Byline spec's `role: bot` flag is the relevant signal, and the identity question shifts from "who is this person" to "what system generated this."

For each of these, attribution (Byline), control (ActivityPub actor), provenance (server signatures), and IP rights (TDM/consent metadata) can point to different entities. The normalized author object in the adapter should carry enough information to populate all four output channels without assuming they converge on a single identity.

### The deeper point: attribution vs. authentication

The loose, consensual, mutual-link method of identifying authorship (`rel="me"`, `fediverse:creator`, Byline profile links) serves a fundamentally different purpose than authenticated identity. Attribution is about credit and discoverability — it answers "who should be named when this work is cited?" Authentication is about enforcement — it answers "who has the authority to act on or against this work?"

These purposes require different identity infrastructure. Attribution works with pseudonyms, collective names, and unverifiable claims. Authentication requires cryptographic keys, institutional credentials, or legal identity documents. The Byline spec and `fediverse:creator` operate in the attribution space. ActivityPub signatures and OAuth tokens operate in the authentication space. Confusing the two leads to either over-requiring identity for simple credit (demanding legal names for blog bylines) or under-requiring identity for consequential actions (allowing anonymous actors to delete federated objects).

**Anonymity and pseudonymity sit differently in each layer.** A pseudonymous author can be fully attributed (their pen name appears in Byline elements, their pseudonymous fediverse account is in `fediverse:creator`, their JSON-LD schema uses the pseudonym). They can have full technical control (their server's keys sign the ActivityPub objects). They can express AI consent preferences (a `noai` declaration from a pseudonym is as legally valid as one from a named person — copyright attaches to the work, not the identity). What they cannot easily do is *enforce* those preferences through legal mechanisms, because legal enforcement generally requires identification. The reverse is also true: a pseudonymous author is harder to hold legally accountable for defamatory or infringing content.

This isn't a problem for the plugin to solve — it's a constraint to design around. The normalized author object should carry whatever identity information the author has chosen to provide, without requiring more. The output channels should function correctly whether the author is "Jane Doe, staff reporter at The Daily Example" or "ghostwriter42" or "Anonymous" or "The Editorial Board." The identity spectrum is not a hierarchy from worse to better — it's a set of tradeoffs between discoverability, privacy, enforcement power, and accountability that each author navigates for themselves.

The Byline identity plugin operates in the attribution layer. It should not pretend to solve authentication, and it should not require authentication to function. But it should be designed so that when stronger identity mechanisms exist (ActivityPub cryptographic signatures, IndieWeb `rel="me"` mutual verification, institutional email domain verification), they can be layered on top of the attribution data without architectural changes.

### The permanence problem: right to be forgotten in a federated attribution system

The better a system is at attributing work to an author — which is exactly what this plugin is designed to do — the harder it becomes for that author to exercise a right to be forgotten. Rich structured identity metadata in feeds, JSON-LD schema, and fediverse tags creates exactly the kind of permanent, machine-readable, cross-platform chain from work to person that makes deletion or disassociation difficult. This is a political problem as much as a technical one.

**Why permanence is a power question.** The right to be forgotten (GDPR Article 17 and analogues elsewhere) exists because a public record permanently tied to an identifiable individual is a tool of power — it can be wielded by the individual (to build reputation, claim credit, enforce IP) or against them (surveillance, harassment, legal liability, political persecution). The same structured metadata chain that lets a reporter get proper credit for investigative journalism also lets an authoritarian government identify, locate, and target the author of dissident writing. The authentication that would be needed to exercise deletion rights is the same authentication that would confirm identity to an adversary.

And it's not just about deleting the work. An author might want to keep published work accessible but sever it from their identity. A journalist who covered organized crime might need to become unfindable. A political dissident who published under their real name might need that name disconnected after a regime change. A person who transitions might want pre-transition bylines updated or unlinked. A whistleblower might need to retroactively anonymize their contributions. These aren't edge cases — they're the conditions under which the right to be forgotten was designed to operate.

**What federation does to deletion.** In a centralized system (a WordPress site you control), identity severance is straightforward: change the byline, update the metadata, done. But the moment structured identity enters a syndication feed or a federated protocol, it escapes your control. Feed readers cache Byline elements locally. Search engines index JSON-LD schema into knowledge graphs. AI training pipelines ingest feed content at crawl time and never re-check. Mastodon instances replicate `fediverse:creator` link previews across every server that fetched them. The attribution chain is now distributed across systems you have no relationship with and no ability to contact.

ActivityPub has a `Delete` activity, but it's advisory — receiving servers can honor it or ignore it. Mastodon generally respects `Delete` for posts, but federated copies on other instances are handled inconsistently. There is no mechanism for "delete my identity from this work but leave the work published." You can delete the object or leave it, but you can't selectively sever the attribution chain. The fediverse does not have a `Disattribute` activity, and the pre-FEP discussion (#2358) was rejected before it could even get to questions of identity severability.

**Implications for the plugin.**

**Make identity removable from the origin.** If an author is removed from a post's attribution in WordPress, the next feed generation should reflect that — Byline elements, `fediverse:creator` tags, and JSON-LD schema should all update. This is the minimum viable right-to-be-forgotten implementation: control what your server says going forward, even knowing that federated and cached copies will persist. An `Update` activity sent via ActivityPub *may* propagate the change to instances that honor it, but this cannot be guaranteed.

**Support disassociation as distinct from deletion.** An author might want to remove their identity from past work without deleting the work itself. The normalized author object needs a state beyond present/absent — something like "was attributed, now withdrawn" — so that feeds can stop emitting the attribution without creating a data integrity hole where the post appears unattributed. The `byline:author` element for a withdrawn author could be replaced with a generic "Author Removed" or the organizational byline, preserving the fact that the post had a specific authorship structure without identifying who.

**Make AI consent retroactively assertable.** The per-author AI consent mechanism (WP-06) intersects here directly. An author exercising a right to be forgotten from AI training datasets needs a machine-readable signal that can be applied *after* initial publication, not just at publication time. The TDM-Reservation header and `robots` meta tag should be updateable per-author, and the update should propagate through feeds so that any system re-crawling the feed picks up the changed consent. This won't reach systems that already ingested the content, but it establishes a dated, machine-readable record of when consent was withdrawn — which has evidentiary value if enforcement becomes possible.

**Be honest about the limits.** The plugin's documentation should be transparent that structured identity in federated and syndicated contexts creates persistence that cannot be fully reversed. This isn't a reason not to build it — attribution is genuinely valuable and most authors want it — but authors should understand the tradeoff they're making when they opt into rich identity metadata across multiple output channels. The plugin should surface this tradeoff in its UI, not bury it.

**Pseudonymity as partial mitigation.** An author who publishes under a pen name and links it to a pseudonymous fediverse account has substantially more room to exercise a de facto right to be forgotten. They can abandon the pseudonym without the structured metadata chain leading back to their legal identity. The plugin's identity spectrum (fully identified → pseudonymous → anonymous) is also an exposure spectrum, and the plugin should support authors moving along it over time — not just choosing a fixed point at the moment of first publication. Practically, this means the plugin should handle byline changes gracefully: if Jane Doe's posts are re-attributed to "J.D." or to the organizational byline, the feed output should reflect the new attribution without leaving traces of the old one in the XML or JSON-LD.

**The design principle.** Every piece of identity metadata the plugin emits should be reversible from the origin server, even if downstream persistence is beyond the plugin's control. The plugin should never emit identity data that the author cannot later ask to have removed from the origin. And the plugin should never make it harder to remove identity than the underlying WordPress system already does — if WordPress lets you change a post's author, the plugin should let that change flow cleanly to every output channel.

## LLM discoverability and credit

### The problem

When an AI system consumes a feed or crawls a page, the author attribution it can extract from standard feed elements is minimal: a name, maybe an email (RSS), maybe a URL (Atom, JSON Feed). There is no structured way to convey *who this person is*, *what their expertise is*, or *why their perspective on this topic matters*.

This means AI-generated summaries and citations strip the context that makes authorship meaningful. "According to an article by Jane Doe" tells the reader nothing. "According to Jane Doe, a staff investigative reporter at The Markup covering surveillance technology" tells the reader everything.

### How Byline metadata helps

If an AI system is consuming a feed with Byline data, the structured `byline:person` element with `context` and `affiliation` provides exactly the richer attribution signal that plain-text bylines lack. The `byline:perspective` element tells the AI system whether it is looking at reporting, analysis, opinion, satire, or sponsored content — a distinction that matters enormously for responsible summarization.

This is not hypothetical. AI search products (Perplexity, Google AI Overviews, ChatGPT search) already consume web content and generate attributed summaries. The quality of those attributions is limited by the quality of the structured metadata available.

### Technical SEO: JSON-LD convergence

The same author data that feeds Byline elements should also produce JSON-LD schema on HTML pages. This is the SEO side of the same coin — Google's E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) framework rewards structured author identity.

The relevant schema structures:

- `Article` with `author` as an array of `Person` objects (multi-author support).
- Each `Person` with `name`, `url`, `description`, `sameAs` (array of social profile URLs — identical data to Byline's `byline:profile` elements).
- `publisher` as an `Organization` (same data as Byline's `byline:org`).
- `Article.creditText` for explicit attribution/licensing requirements.

WordPress schema output today (via Yoast, Rank Math, etc.) handles single authors reasonably well but multi-author attribution poorly. A plugin that generates both Byline feed elements and JSON-LD Person/Article schema from the same normalized author data — the adapter pattern from the adoption strategy — would be more coherent than bolting together separate feed and schema plugins.

The practical path: offer a JSON-LD output component that hooks into `wp_head` and produces Article + Person schema derived from the same adapter that feeds the Byline output. Filter every field so existing schema plugins can integrate rather than conflict. If Yoast or Rank Math is detected, offer a compatibility mode that extends their schema rather than replacing it.

### Single source of truth

The architectural principle: author data entered once (in WordPress user profiles or multi-author plugin profiles) flows to:

1. HTML JSON-LD schema (for search engines and AI crawlers).
2. RSS/Atom Byline elements (for feed readers).
3. ActivityPub Actor objects (for the fediverse).
4. Content rights metadata (for AI training consent signaling).

No duplicate data entry. No divergence between what the feed says and what the HTML says. The adapter pattern from the Byline adoption strategy is the foundation — each multi-author plugin adapter resolves to the same normalized author object, and each output channel consumes that object.

## Intellectual property protection and harvesting resistance

### Honest framing

No technical mechanism can prevent a determined crawler from harvesting publicly accessible content. `robots.txt`, `ai.txt`, meta tags, and TDM headers are all advisory — they depend on the crawler choosing to honor them. Any tooling that claims otherwise is not credible.

What structured metadata *can* do is make publisher intent unambiguous, create an evidentiary record of rights expression, and provide practical levers for controlling the tradeoff between discoverability and exposure.

### Signaling layers

**TDMRep (Text and Data Mining Reservation Protocol).** An emerging W3C specification that lets publishers express machine-readable preferences about text and data mining. A Byline identity plugin could include TDM metadata alongside author identity in feed output and HTML headers: "here is who wrote this, and here are the terms under which it may be mined."

**`ai.txt` convention.** Analogous to `robots.txt` but specifically for AI training crawlers. A plugin settings page that generates and maintains an `ai.txt` file based on site-wide or per-author preferences would lower the barrier to adoption.

**C2PA (Coalition for Content Provenance and Authenticity).** A standard for content provenance that is currently image/video focused but has potential for text content. Worth monitoring but premature to implement for blog posts.

**Creative Commons machine-readable metadata.** The `cc:license` RSS element already exists. A Byline plugin could output it alongside Byline elements, creating a complete picture: "this person wrote this, with this perspective, under this license."

### Feed-level gating

Offer a choice between full-content and excerpt-only feeds. Excerpt-only feeds with full Byline identity give publishers maximum discoverability with minimum content exposure. The AI search engine sees "Jane Doe, staff reporter at X, wrote an analysis piece about Y" and must visit the publisher's site for the full text — where the publisher controls access, monetization, paywalls, and tracking.

This is not new — WordPress has offered excerpt vs. full-content feeds since forever. What is new is pairing the excerpt feed with rich structured identity so the excerpt *itself* carries attribution value rather than being an anonymous teaser.

### Per-author consent

On a multi-author site, different authors may have different preferences about AI use of their work. A staff reporter may want maximum reach. A guest contributor may want to opt out of AI training entirely. A columnist may want their opinion pieces excluded but their reported pieces included.

A per-author or per-post metadata field for AI training consent, expressed in both feed metadata and HTML meta tags, would give publishers granular control. Implementation:

- User meta field: `byline_ai_training_consent` with values `allow`, `deny`, `unset`.
- Post meta field: `_byline_ai_consent` to override author-level preference per post.
- Output: `<meta name="robots" content="noai, noimageai">` on HTML pages where consent is denied. TDMRep headers for the same. Feed items for opted-out authors could carry a rights element or be excluded from the feed entirely (configurable).

This is genuinely novel. Nobody is doing per-author AI consent in structured metadata today. It would be an attention-getting feature for the journalism community, where this debate is live and urgent.

### The tension is the feature

The reason this matters is that discoverability and protection are not a binary choice. Publishers need a spectrum of control:

- Maximum visibility: full content in feeds, full Byline identity, JSON-LD schema, AI training allowed.
- Visible but protected: excerpt feeds with full identity, AI training denied, TDM reservation expressed.
- Minimal exposure: no feed output, minimal schema, comprehensive AI opt-out.

A single plugin that offers this spectrum, grounded in the same normalized author data, is more useful than separate tools for "SEO," "feeds," and "AI protection" that each have their own configuration and data model.

## Component roadmap

Building on the Byline adoption strategy, the broader vision breaks into incremental components. Each is useful on its own; together they form a coherent author identity and content provenance layer.

### Component 1: Byline feed output (from adoption strategy)

The adapter pattern, multi-author plugin support, RSS2/Atom output, perspective meta field. This is the foundation. Ship first on wp.org.

### Component 2: JSON-LD schema output

Article + Person + Organization schema on post pages, derived from the same normalized author data. Compatibility mode for Yoast/Rank Math. Multi-author support in schema (array of Person objects). `sameAs` populated from the same profile links that feed `byline:profile`.

### Component 3: Content rights and AI consent

Per-author and per-post AI training consent fields. TDM headers. `ai.txt` generation. Creative Commons metadata in feeds. Excerpt-only feed option with full identity metadata. This component is the most editorially complex but also the most likely to drive adoption in the journalism community.

### Component 4: ActivityPub and fediverse bridge

Output `fediverse:creator` meta tags from normalized author data — this is the most immediate win, working with Mastodon's existing support (launched July 2024 for journalism use cases). For multi-author posts, output multiple `fediverse:creator` tags; Mastodon currently displays only the first but has stated intent to support multiple authors and introduced an `authors` array in its REST API (PR #30846). The protocol-level pre-FEP for co-author representation (#2358) was rejected, confirming that the practical path runs through HTML-level mechanisms and existing ActivityPub primitives rather than new protocol extensions. Monitor the ActivityPub plugin for new filter hooks on `attributedTo` and actor management (Discussion #547) that would allow injecting multi-author data without protocol changes.

### Component 5: IndieWeb integration

`rel="me"` output from Byline profile links. WebSub/PuSH hub advertising in feeds alongside Byline data. Microformats2 `h-card` output alongside or instead of JSON-LD for sites in the IndieWeb ecosystem. Webmention support for cross-site author verification.

### Component 6: ActivityPub C2S as a publication protocol (forward-looking)

This component is architecturally anticipated but not near-term. It depends on the C2S ecosystem maturing — which is currently at a discussion stage but has active energy behind it (see the C2S section below for full context). The adapter pattern should be designed so that a C2S output channel is natural to add when the infrastructure is ready.

## The neglected ActivityPub C2S API

### Background

ActivityPub defines two protocols: Server-to-Server (S2S) for federation between instances, and Client-to-Server (C2S) for users and applications to interact with their accounts on servers. The fediverse runs almost entirely on S2S. C2S has been largely ignored.

The current state is stark. The AP C2S API is not widely implemented in servers, and almost no clients exist for it. Mastodon has never meaningfully implemented C2S — home timelines and posting via C2S return 404 errors. Pleroma had basic C2S working at one point. The practical fediverse uses S2S federation between servers, with the Mastodon Client API (a proprietary REST API not part of the W3C spec) as the de facto standard for client-to-server communication.

The reasons for neglect go beyond inertia. As trwnh (a prominent AP contributor) articulated on SocialHub in November 2024: the C2S API suffers from an "impedance mismatch" — a social network wants timelines, search, streaming, and bookmarks, while AP C2S was written for simple resource manipulations and push notifications. It's not that C2S is broken; it's that it was designed for a different kind of interaction than what Mastodon-style social networking demands.

A "NextGen ActivityPub Social API" effort was proposed on SocialHub in November 2024 by Steve Bate, aiming to bring C2S to feature parity with the Mastodon Client API through a set of FEPs. The approach includes a façade that proxies the Mastodon API while exposing a standard C2S interface, plus a reference client implementation. Pixelfed's dansup expressed interest in adding C2S support to the Loops short video platform. The effort is at the discussion and prototyping stage.

Sources: socialhub.activitypub.rocks/t/nextgen-activitypub-social-api/4733, socialhub.activitypub.rocks/t/the-activitypub-client-api/3186, socialhub.activitypub.rocks/t/activitypub-client-to-server-faq/1941.

### Why C2S matters for WordPress publishing

WordPress is not a typical fediverse node. It's a publisher — it creates content and pushes it out. It doesn't need timelines, search, or streaming. It needs exactly what C2S was originally designed for: a client posting activities to an outbox.

Consider what WordPress actually is in the ActivityPub model. The WordPress ActivityPub plugin currently acts as both client *and* server in a single package — it creates `Article` objects, wraps them in `Create` activities, and handles federation delivery, all internally. This coupling is where the post_author/actor synchronization issue (#2353) originated: the plugin has to simultaneously resolve "who is the actor for this Create activity?" and "how do I deliver it to followers?"

C2S decouples these concerns. In a C2S model:

1. WordPress acts as the **client**. It composes a `Create` activity containing an `Article` object with full multi-author `attributedTo` metadata (names, bios, profile links — all the Byline data).
2. The client POSTs this activity to an **outbox endpoint** on an AP server.
3. The **server** handles federation delivery — signing, inbox discovery, delivery retries — without needing to know anything about WordPress's internal authorship model.

The editorial concern (who wrote this, how should it be attributed) is cleanly separated from the federation concern (how does it get delivered). WordPress controls the content and metadata; the AP server controls distribution.

This model was explored by the LAUTI community calendar project, where Bonfire Networks suggested implementing C2S instead of S2S precisely because it's simpler for a publishing application that wants to connect to an existing AP actor rather than becoming its own federation node.

Source: socialhub.activitypub.rocks/t/possible-c2s-implementation-in-lauti/8173.

### Multi-author attribution via C2S

The AP spec states: "When a Create activity is posted, the actor of the activity SHOULD be copied onto the object's `attributedTo` field." But `attributedTo` is not constrained to a single value. A C2S client could POST a Create activity where:

- `actor` is the publishing account (the WordPress site or primary author).
- `object.attributedTo` is an array of all co-authors, each an Actor URI.

This is spec-compliant today. The challenge has been that nobody implements C2S, so nobody has tested multi-author `attributedTo` arrays through this path. A WordPress plugin that does this would be a concrete demonstration of C2S's value for the publishing use case.

### Content provenance via C2S

C2S is also relevant to the content rights and provenance questions. A `Create` activity is a structured JSON-LD object. The client (WordPress) controls everything that goes into it before posting to the outbox. This means all Byline metadata, rights signals, license declarations, TDM reservations, and AI consent flags could be embedded directly in the activity object:

```json
{
  "@context": ["https://www.w3.org/ns/activitystreams", ...],
  "type": "Create",
  "actor": "https://example.com/authors/jane",
  "object": {
    "type": "Article",
    "attributedTo": [
      "https://example.com/authors/jane",
      "https://example.com/authors/alex"
    ],
    "content": "...",
    "name": "Article Title",
    "cc:license": "https://creativecommons.org/licenses/by-nc/4.0/",
    "tdm:reservation": "https://example.com/tdm-policy"
  }
}
```

This is more powerful than the HTML meta tag approach (`fediverse:creator`, TDM headers) because the metadata travels *with the activity object* through federation rather than requiring recipients to fetch and parse the origin page. Every server that receives the activity has the full provenance and rights information embedded in the object itself.

### Realistic assessment

C2S is neglected for real structural reasons, not just inertia:

- No popular fediverse clients use it.
- Most servers don't implement it (Mastodon notably does not).
- The Mastodon Client API is the de facto standard, and the Mastodon team has expressed discomfort with other servers cloning it but hasn't offered C2S as an alternative.
- The NextGen Social API FEP effort is at the discussion stage with no shipped implementations.
- Authentication and authorization for C2S are underspecified in the original standard.

For this project, C2S is a **forward-looking architectural consideration**, not a near-term implementation target. The immediate wins are `fediverse:creator` tags (Component 4), Byline feeds (Component 1), and JSON-LD schema (Component 2) — these work today with deployed infrastructure.

However, the adapter pattern (normalized author data flowing to multiple output channels) should be designed so that a C2S output channel is architecturally natural to add. If C2S revives — and the NextGen Social API effort, the LAUTI exploration, and Pixelfed/Loops interest suggest there is energy in that direction — WordPress multi-author content would be one of the strongest use cases for it. The publishing model (create structured content, post to outbox, let the server handle delivery) is exactly what C2S was designed for, even if the social networking model (timelines, search, streaming) is not.

The strategic play: build Components 1-5 using today's infrastructure (feeds, HTML meta tags, JSON-LD, `fediverse:creator`), but keep the normalized author data interface clean enough that a C2S adapter can slot in alongside the feed adapter, the schema adapter, and the `fediverse:creator` adapter when the time is right.

## Naming and positioning

The broader vision is no longer just "a Byline feed plugin." It is closer to "structured author identity and content provenance for WordPress." The wp.org plugin should start with the Byline feed name and scope, then grow into the broader positioning as components ship.

For the journalism and professional writing community, the framing should lead with the editorial problem: "your byline should travel with your work — with your credentials, your perspective, your rights, and your identity intact."

For the IndieWeb community: "own your identity across every channel your writing reaches."

For the technical SEO community: "E-E-A-T compliance from the same author data that powers your feeds."

For the AI-concerned community: "structured rights expression so your consent preferences are machine-readable, not just implied."

For authors concerned about privacy and safety: "your identity metadata is always reversible from your site — if you need to change a byline, withdraw attribution, or move from named to pseudonymous, the plugin respects that change across every output channel it controls."

### Design principle: reversible identity

Every piece of identity metadata the plugin emits should be reversible from the origin server, even if downstream persistence is beyond the plugin's control. The plugin should never emit identity data that the author cannot later ask to have removed from the origin. And the plugin should never make it harder to remove identity than the underlying WordPress system already does — if WordPress lets you change a post's author, the plugin should let that change flow cleanly to every output channel. This principle applies across all components, from Byline feed elements to JSON-LD schema to `fediverse:creator` tags to AI consent metadata. See "The permanence problem" in the attribution/control/provenance/rights analysis for the full rationale.
