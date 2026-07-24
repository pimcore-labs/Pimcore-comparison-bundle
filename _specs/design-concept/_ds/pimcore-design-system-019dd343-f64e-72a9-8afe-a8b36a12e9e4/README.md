# Pimcore Design System

A design system for **Pimcore** — the open-core Data & Experience Management platform (PIM, MDM, DAM, CDP, DXP/CMS, Digital Commerce).

This system gives a design agent everything it needs to produce on-brand Pimcore artifacts: marketing pages, slides, app screens, mocks, and prototypes.

---

## What is Pimcore?

Pimcore is an enterprise data + experience management platform out of Austria. The flagship product, **Pimcore Platform™**, unifies six capabilities in one open-core stack:

- **PIM** — Product Information Management
- **MDM** — Master Data Management
- **DAM** — Digital Asset Management
- **CDP** — Customer Data Platform
- **DXP / CMS** — Digital Experience Platform / Content Management System
- **Digital Commerce** — B2B / B2C / D2C framework

It is sold in three editions (Community, Enterprise, Cloud/PaaS) and is used by 118,000+ companies. Built on PHP/Symfony with a Studio admin UI.

### Surfaces this system covers

1. **pimcore.com — marketing website.** Bold purple hero blocks, big claims, dense product nav, lots of "Get a Demo" CTAs.
2. **Pimcore Studio — the admin app.** Dense, ExtJS-roots interface: tree explorer, tabbed workspace, data grids, inspectors. Modern Studio UI bundle is a React refresh of the classic admin.
3. **docs.pimcore.com — developer documentation.** Standard MkDocs-style three-column layout: nav, content, on-page TOC.

### Source materials used to build this system

- Eleven uploaded official logo + favicon SVGs (see `assets/`)
- pimcore.com public pages (product overviews, FAQ, blog, CMS / DXP / Personalization sections)
- github.com/pimcore (core repo + studio-ui-bundle, admin-ui-classic-bundle)
- docs.pimcore.com (developer documentation portal)
- Brandfetch + logotyp.us listings for confirming the official palette (`#6428B4` Purple Heart, plus Black and White)

> ⚠️ **No codebase or Figma file was attached.** Visual fidelity for app screens is based on public screenshots and the public github source. If you want pixel-perfect Studio UI, please attach the codebase (or Figma) via the Import menu and I'll re-do the UI kit against ground truth.

---

## Index

| File / folder | What's in it |
|---|---|
| `README.md` | This document — brand overview, content & visual foundations, iconography. |
| `SKILL.md` | Agent Skills manifest — lets this folder run as a portable skill. |
| `colors_and_type.css` | All design tokens as CSS custom properties + semantic type classes. |
| `assets/` | Logos (3 lockups × 3 colors), favicons, generic placeholder illustration. |
| `preview/` | Small HTML cards that populate the Design System tab. |
| `ui_kits/website/` | Pimcore.com marketing website UI kit. |
| `ui_kits/studio/` | Pimcore Studio (admin app) UI kit. |
| `ui_kits/docs/` | Pimcore documentation site UI kit. |

---

## Content fundamentals

**Voice** is bold, confident, and slightly disruptive — but always B2B-credible. Pimcore positions itself against "rigid vendor roadmaps" and "standardized shop software." It uses big, almost manifesto-style claims followed by concrete enterprise specifics.

**Tone characteristics**

- **Direct second person.** "You" and "your business," not "users" or "customers." Active voice.
- **Short, punchy headlines** — often a noun phrase + verb, sometimes em-dashed. Examples seen on pimcore.com:
  - *"Product Data Determines Growth."*
  - *"Made to build Anything! Really."*
  - *"Uncompromised Data & Experience Management for Realists, disruptive Thinkers and those who dare to face reality!"*
- **Manifesto exclamation points** appear on the homepage but vanish in product copy and docs. Don't sprinkle them everywhere.
- **Body copy is enterprise-formal but not stiff.** Compound benefit sentences: "Centralize master data, streamline content for all channels, and drive conversions with personalized, data-driven experiences."
- **Sentence case** for almost everything. Headlines are sentence case (sometimes Title Case for very short hero claims). UI labels are sentence case.
- **Trademark discipline.** "Pimcore Platform™", "Pimcore Copilot", "Pimcore Academy" — capital P, no abbreviation to "pim core" or lowercase.
- **Acronyms are first-class.** PIM, MDM, DAM, CDP, DXP, CMS — never spelled out after first introduction. Often stacked as "PIM / MDM / DAM / CDP / DXP / CMS" with slashes or commas.
- **No emoji** in product copy. GitHub READMEs and dev blog posts use them lightly (📢 🌍 📖 💪) but the marketing site is emoji-free. Don't introduce new emoji.
- **Numbers as proof.** "118,000+ companies", "200+ file types", "24/7 support", "1.8 million products." Use real numbers when you have them; don't invent.

**Things to avoid**

- AI-startup gushing ("revolutionize", "magical", "delightful")
- Conversational filler ("Hey there!", "Welcome friend")
- Lowercase product names
- "Click here" — Pimcore uses imperative button verbs: *Get a Demo*, *Read more*, *Download*, *Get started*

**Sample headline + subhead pairings (for reference)**

> **The Future of Data & Experience Management**
> Fastest time-to-market and rapid digitization with the enterprise data and experience management platform for PIM/MDM, DAM, DXP/CMS, CDP, and digital commerce.

> **Any Data. Any Model. Any Channel.**
> Centralize master data, streamline content for all channels, and drive conversions with personalized, data-driven experiences.

---

## Visual foundations

### Color
- **One bold primary**: Purple Heart `#6428B4`. Used for primary CTAs, key headlines, the wordmark, and a small set of full-bleed brand panels.
- **Heavy use of white space** as the dominant page surface. Purple is the accent, not the wash.
- **Black and near-black ink** for text and dark sections. The dark mode of the brand is `#0c0a14` ink rather than pure `#000`.
- **No gradients** in the brand layer. The site does use dark photographic hero images with a subtle purple tint, but linear gradients (especially the bluish-purple SaaS-AI gradient) are avoided.
- **Semantic colors** are conventional enterprise: green success, amber warning, red danger, blue info — desaturated to sit politely next to brand purple without competing.

### Typography
- **Single sans family** for everything (display, body, UI). The brand favors a geometric humanist sans similar to Inter / Helvetica Now. **We're substituting Google Fonts Inter** as the closest free match — please supply the official font files (e.g. Pimcore's licensed copy of their wordmark font) to upgrade fidelity.
- **Strong weight contrast.** Display uses 700–800. Body uses 400. Buttons use 600.
- **Tight tracking on big headlines** (`-0.02em`), normal tracking on body, wide tracking + uppercase on small eyebrow labels (`+0.08em`).
- **Mono font** (JetBrains Mono) for code samples in docs and inline `code` mentions.

### Spacing & layout
- **4px base grid.** All tokens are multiples of 4.
- **Generous vertical rhythm** on the marketing site — sections are typically 96–128px of vertical padding.
- **Dense in-app.** Studio's grid rows are 28–32px tall. App body text is 13px. Marketing body is 16–18px.
- **12-column layouts** on the website, max content width ~1280px.

### Backgrounds
- **Predominantly white** with charcoal text.
- **Full-bleed purple sections** for moments of brand emphasis (CTA strip, edition picker, conference banners).
- **Dark sections** (`--pc-ink-900`) with white type for product showcases and "future of" framing.
- **No repeating patterns or hand-drawn illustrations.** Pimcore's visual layer leans on screenshots and structured product diagrams, not whimsy.
- **Subtle imagery treatment**: photography is cool-toned, slightly desaturated, often professional / corporate. Product UI screenshots are framed in soft drop shadows on white.

### Borders, corners, shadows
- **Subtle 1px borders** on cards (`--pc-border` = `#ddd9e3`).
- **Modest corner radii** — 4px for inputs, 8px for buttons & cards, 12px for big surfaces. Nothing pill-shaped except chips/tags.
- **Soft elevation only.** `--pc-shadow` is a small offset + blur with low opacity. No big drop shadows. One brand-tinted shadow exists (`--pc-shadow-purple`) for hero CTAs.

### Motion
- **Restrained.** Default transition is 180ms with a standard ease (`cubic-bezier(0.2, 0, 0, 1)`).
- **Hover states** are color shifts (purple → purple-600, ink → ink-700) and subtle 1–2px translate-Y on cards, never scale.
- **Press states** drop one more shade and remove translate.
- **No bounces, no spring physics, no entrance animations on marketing pages.** The brand wants to feel reliable and enterprise, not playful.

### Transparency, blur
- **Sparingly.** Backdrop-blur appears in the sticky top nav of the marketing site (`backdrop-filter: blur(12px)` over `rgba(255,255,255,0.85)`).
- **No frosted glass cards or inset glassmorphism** — that's a different brand language.

### Cards
- White surface, 1px `--pc-border`, 12px radius, `--pc-shadow-sm` at rest, `--pc-shadow` on hover. Padding 24px. Title in `--pc-h4`, body in `--pc-body`. Optional `--pc-eyebrow` purple label above the title.

### Layout rules
- Sticky top nav, ~72px tall.
- CTA "Get a Demo" pinned right in the nav and reappears in the footer.
- Content max-width 1200–1280px. Two-column on desktop, single-column under 768px.
- App: classic three-pane layout (left tree, center workspace tabs, right inspector). Top utility bar with logo + global search.

---

## Iconography

**Pimcore uses an outline-style icon set** at 16px (Studio) and 20–24px (marketing). Stroke weight is consistent (~1.5–2px), corners are rounded, and icons fit on a 24px square grid.

In the live admin, the historical bundle is built on **ExtJS** with a bundled glyph font + raster sprites. The newer Studio UI bundle uses **SVG icons rendered via a custom React icon component**. Neither set is published as a public CDN.

**This system substitutes [Lucide](https://lucide.dev) icons** — open source, MIT-licensed, identical visual character (1.5px outline, 24px grid, rounded joins). It's available via CDN at `https://unpkg.com/lucide-static/font/lucide.css` or as React components. Use Lucide everywhere unless the official Pimcore icon source becomes available.

**⚠️ Substitution flagged.** If you want pixel-perfect Pimcore icons, please attach the `studio-ui-bundle` or `admin-ui-classic-bundle` so I can extract them.

**Other usage notes**

- **No emoji** in marketing or app surfaces.
- **No unicode-as-icon** decoration (no ✓, →, ★ shorthand). Use a real SVG.
- **Logo lockups** — three are provided in `assets/`:
  - `logo-wordmark-*.svg` — full wordmark "pimcore"
  - `logo-claim-*.svg` — wordmark + tagline lockup
  - `logo-mark-*.svg` — small wordmark used inside the app
  - `favicon-*-on-*.svg` — square favicons with the "p" mark
- **Minimum logo width** ~96px. Below that, use the favicon mark instead.
