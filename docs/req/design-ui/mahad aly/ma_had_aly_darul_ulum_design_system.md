---
name: "Ma'had Aly Darul Ulum — Academic & Pesantren Portal"
description: "An authoritative, scholarly Indonesian higher-education design system blending pesantren heritage with modern institutional clarity."
---

## Overview

The **Ma'had Aly Darul Ulum** design system unifies the institutional authority of national pesantren councils, the academic clarity of Indonesian university portals, and the scholarly *turats* heritage of Ma'had Aly. Designed for prospective *mahasantri*, parents, researchers, and academic partners, it evokes trust, intellectual rigor, and spiritual dignity.

The visual direction pairs **Editorial Minimalism** with **Corporate Modern** structure: generous whitespace, crisp typographic hierarchy, a disciplined 12-column desktop grid, and tonal surface layering in light mode.

## Colors

The palette anchors on deep emerald green (`primary` and `primary-container`) representing Islamic scholarship and growth, balanced by warm gold (`secondary` and `secondary-container`) for academic distinction and calls to action, and scholarly navy (`tertiary`) for research and accreditation highlights. Surfaces use clean white and soft sage-tinted ivory neutrals for comfortable long-form reading.

- **Primary (`#003426` / `#0F4C3A`):** Used for primary navigation, institutional banners, and primary action buttons.
- **Secondary (`#7B5900` / `#FCCA66`):** Used for admissions highlights, accreditation badges, and key accent moments.
- **Tertiary (`#0F2D52`):** Used for academic research, journal, and governance tags.
- **Surfaces (`#F3FBF4` / `#FFFFFF`):** Tonal elevation steps from `surface` (`#F3FBF4`) to `surface-container-lowest` (`#FFFFFF`) separate sections without heavy borders.

```yaml
colors:
  primary: "#003426"
  on-primary: "#FFFFFF"
  primary-container: "#0F4C3A"
  on-primary-container: "#82BBA4"
  secondary: "#7B5900"
  on-secondary: "#FFFFFF"
  secondary-container: "#FCCA66"
  on-secondary-container: "#755400"
  tertiary: "#0F2D52"
  on-tertiary: "#FFFFFF"
  tertiary-container: "#29446A"
  on-tertiary-container: "#97B1DD"
  error: "#BA1A1A"
  on-error: "#FFFFFF"
  error-container: "#FFDAD6"
  on-error-container: "#93000A"
  background: "#F3FBF4"
  on-background: "#161D19"
  surface: "#F3FBF4"
  surface-dim: "#D4DCD5"
  surface-bright: "#F3FBF4"
  surface-container-lowest: "#FFFFFF"
  surface-container-low: "#EEF6EF"
  surface-container: "#E8F0E9"
  surface-container-high: "#E2EAE3"
  surface-container-highest: "#DCE5DE"
  on-surface: "#161D19"
  surface-variant: "#DCE5DE"
  on-surface-variant: "#404944"
  outline: "#707974"
  outline-variant: "#BFC9C3"
  inverse-surface: "#2A322E"
  inverse-on-surface: "#EBF3EC"
  inverse-primary: "#99D3BA"
```

## Typography

A three-role typographic hierarchy pairs **Yrsa** (transitional serif) for authoritative headlines and scholarly quotes, **Lato** (humanist sans-serif) for crisp Indonesian body copy and navigation, and **Spline Sans Mono** for dates, accreditation numbers, and institutional statistics.

```yaml
typography:
  display-lg:
    fontFamily: "Yrsa"
    fontSize: 48px
    fontWeight: 700
    lineHeight: 54px
    letterSpacing: -0.5px
  display-lg-mobile:
    fontFamily: "Yrsa"
    fontSize: 32px
    fontWeight: 700
    lineHeight: 38px
    letterSpacing: -0.25px
  display-md:
    fontFamily: "Yrsa"
    fontSize: 36px
    fontWeight: 700
    lineHeight: 42px
    letterSpacing: -0.25px
  display-md-mobile:
    fontFamily: "Yrsa"
    fontSize: 28px
    fontWeight: 700
    lineHeight: 34px
    letterSpacing: -0.15px
  headline-lg:
    fontFamily: "Yrsa"
    fontSize: 32px
    fontWeight: 600
    lineHeight: 38px
    letterSpacing: 0px
  headline-md:
    fontFamily: "Yrsa"
    fontSize: 24px
    fontWeight: 600
    lineHeight: 30px
    letterSpacing: 0px
  title-lg:
    fontFamily: "Lato"
    fontSize: 20px
    fontWeight: 700
    lineHeight: 28px
    letterSpacing: 0px
  title-md:
    fontFamily: "Lato"
    fontSize: 16px
    fontWeight: 700
    lineHeight: 24px
    letterSpacing: 0.1px
  body-lg:
    fontFamily: "Lato"
    fontSize: 16px
    fontWeight: 400
    lineHeight: 26px
    letterSpacing: 0px
  body-md:
    fontFamily: "Lato"
    fontSize: 14px
    fontWeight: 400
    lineHeight: 22px
    letterSpacing: 0.1px
  label-lg:
    fontFamily: "Lato"
    fontSize: 14px
    fontWeight: 700
    lineHeight: 20px
    letterSpacing: 0.2px
  label-sm:
    fontFamily: "Lato"
    fontSize: 12px
    fontWeight: 700
    lineHeight: 16px
    letterSpacing: 0.4px
  mono-md:
    fontFamily: "Spline Sans Mono"
    fontSize: 13px
    fontWeight: 500
    lineHeight: 18px
    letterSpacing: 0px
```

## Layout & Spacing

The desktop layout uses a **Fixed-Max-Width 12-Column Grid** (`max-w-7xl`, 1280px container) with `24px` page margins and `24px` column gutters, collapsing cleanly to single-column stacked sections on mobile (`390px`). Spacing follows an 8px base rhythm (`4px` half-step for micro-adjustments).

```yaml
spacing:
  gutter-xs: 4px
  gutter-sm: 8px
  gutter-md: 16px
  gutter-lg: 24px
  gutter-xl: 32px
  section: 64px
  page-margin: 24px
```

## Elevation & Depth

Hierarchy relies on **Tonal Layering** and **Low-Contrast Outlines** (`outline-variant`) rather than heavy drop shadows. Primary cards sit on `surface-container-lowest` (`#FFFFFF`) over `surface` (`#F3FBF4`) or `surface-container-low` (`#EEF6EF`) section bands, using a subtle `1px` border in `outline-variant` and soft ambient elevation only on sticky navigation or interactive hover states.

## Shapes

The shape language balances institutional formality with approachability: crisp `8px` (`DEFAULT`) corners for buttons and inputs, `12px` (`md`) for academic and news cards, `16px` (`lg`) for featured editorial containers, and `9999px` (`full`) for status pills and category chips.

```yaml
rounded:
  sm: 4px
  DEFAULT: 8px
  md: 12px
  lg: 16px
  xl: 24px
  full: 9999px
```

## Motion & Micro-interaction

Motion is restrained, dignified, and purposeful. Interactive hover states on buttons, navigation links, and news cards respond within `150ms` using `ease-out`, while larger section reveals and modal transitions decelerate smoothly over `250ms`. Always respect `prefers-reduced-motion` and animate only `transform`, `opacity`, and `color`.

```yaml
motion:
  state-change:
    duration: 150ms
    delay: 0ms
    timingFunction: ease-out
  surface-enter:
    duration: 250ms
    delay: 0ms
    timingFunction: cubic-bezier(0.2, 0, 0, 1)
  surface-exit:
    duration: 150ms
    delay: 0ms
    timingFunction: cubic-bezier(0.3, 0, 1, 1)
```

## Voice & Content Strategy

- **Tone of voice:** Formal, warm, scholarly, and welcoming (*berwibawa namun ramah*). Write in clear, natural Bahasa Indonesia suitable for an accredited Islamic higher education institution.
- **Capitalisation:** Title Case for navigation items, section headings, and program titles; Sentence case for body copy, news summaries, and form hints.
- **Terminology:** Use authentic Ma'had Aly & pesantren higher-education terms consistently: *Mahasantri* (not *mahasiswa* unless referring to general university equivalents), *Marhalah Ula (M1)* & *Marhalah Tsaniyah (M2)*, *Takhasus* (specialization), *Mudir* (director), *Dewan Masyayikh*, and *Kitab Turats*.
- **Button and action labels:** Use clear verb phrases in Bahasa Indonesia (e.g., *Daftar PMB Sekarang*, *Jelajahi Takhasus*, *Baca Selengkapnya*, *Unduh Brosur*).
- **Numbers and dates:** Use Indonesian date format (`17 November 2026`) and tabular numerals (`tabular-nums`) for years, accreditation scores, and statistics.

## Components

Component implementations live as individual HTML sticker sheet files in
`components/`. Each file contains the canonical, styled HTML components with
`<!-- COMPONENT: name -->` comment markers. Read each file before use.

- **Badge** → `@components/badge.html`: `badge-numeric`, `badge-dot`
- **Button** → `@components/button.html`: `button-primary`, `button-icon`, `button-with-icon`
- **Card** → `@components/card.html`: `card-elevated`, `card-action`, `card-interactive`, `card-interactive-2`
- **Tabs** → `@components/tabs.html`: `tabs-horizontal`, `tabs-with-icon`
