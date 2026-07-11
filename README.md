# GatherPress Targetgroups

**Contributors:** carstenbach  
**Tags:** theater, target-groups, gatherpress  
**Tested up to:** 6.9  
**Stable tag:** 0.1.0  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

[![Playground Demo Link](https://img.shields.io/badge/WordPress_Playground-blue?logo=wordpress&logoColor=%23fff&labelColor=%233858e9&color=%233858e9)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-targetgroups/main/.wordpress-org/blueprints/blueprint.json) [![Build, test & measure](https://github.com/carstingaxion/gatherpress-targetgroups/actions/workflows/build-test-measure.yml/badge.svg?branch=main)](https://github.com/carstingaxion/gatherpress-targetgroups/actions/workflows/build-test-measure.yml)


## Description

GatherPress Targetgroups extends [GatherPress](https://gatherpress.org/) with a hierarchical **Target Group** taxonomy on events. It lets organisers classify events by audience — age ranges, skill levels, or any other grouping — and gives editors a depth control on the Post Terms block that filters the displayed terms to a single level of the hierarchy without exposing meaningless archive links.

### What it does

**Target Group taxonomy (`gatherpress_targetgroup`)**  
Registers a hierarchical, UI-visible taxonomy on `gatherpress_event` and `gatherpress_play` (the Productions post type). The taxonomy has `rewrite => false` because its terms are organisational rather than browsable: there are no meaningful archive URLs, and the term links in the Post Terms block are neutralised on the frontend by the companion [post-terms-block-unlinked](https://github.com/carstingaxion/post-terms-block-unlinked) plugin. Terms are always returned ordered parents-before-children — declared once via the `args` key of `register_taxonomy()` and applied automatically to every `wp_get_object_terms()` call for this taxonomy, including `get_the_terms()`.

**Automatic ancestor storage**  
WordPress only saves the exact term IDs passed to `wp_set_object_terms()`. When an editor assigns only a child term (e.g. *0–5*), its parent (e.g. *Kids*) is not stored automatically. On every `set_object_terms` action for this taxonomy the plugin walks the full ancestor chain of every assigned term and appends any missing ancestors with `$append = true`. This ensures that depth-level filtering in the block always has the complete hierarchy available, regardless of how terms were originally assigned — via the block editor, the classic metabox, the REST API, or programmatically.

**`depthDisplay` block attribute and inspector control**  
Adds a custom `depthDisplay` string attribute to the `core/post-terms` block type (registered server-side at priority 100, after core has registered the block). A higher-order component injects a *Depth* panel into the block inspector sidebar whenever a `core/post-terms` block is set to the `gatherpress_targetgroup` taxonomy. The panel contains a `RadioControl` with three options:

- **All levels** (`all`) — renders every assigned term, ordered parents-before-children. The original block output is returned unchanged.
- **Top level only** (`top`) — keeps only terms whose `parent` is `0` (root terms).
- **Leaves only** (`leaves`) — keeps only terms that are not acting as the parent of another assigned term. The algorithm operates on the assigned subset alone, with no taxonomy-wide queries, and handles mixed-depth assignments and taxonomies deeper than two levels correctly.

For the `top` and `leaves` modes the block output is rebuilt by extracting the opening wrapper `<div>` verbatim from the rendered HTML (preserving all editor-applied block supports — color, typography, border, spacing) and re-rendering only the filtered terms inside it.


## Requirements

- WordPress 6.2 or later
- PHP 7.4 or later
- [GatherPress](https://gatherpress.org/) 0.34.0-alpha-2 or later
- [post-terms-block-unlinked](https://github.com/carstingaxion/post-terms-block-unlinked) — recommended; neutralises the otherwise-broken term archive links on the frontend

## Installation

1. Upload the plugin files to `/wp-content/plugins/gatherpress-targetgroups`.
2. Activate the plugin via the **Plugins** screen.


## Frequently Asked Questions

### Does this work without GatherPress?

No.

### Why are term archive links broken by default?

The taxonomy is registered with `rewrite => false` because target groups are an organisational classification, not a browsable archive. Without the companion post-terms-block-unlinked plugin the term links in the Post Terms block will point to non-existent URLs. Install that plugin alongside this one to have the links neutralised automatically.

### Why does assigning a child term also save its parent?

The `depthDisplay: 'top'` and `depthDisplay: 'leaves'` modes work by filtering the set of *already-assigned* terms. If only a child term is stored, filtering to *Top level only* would produce an empty result. The ancestor-storage hook ensures the full chain is always present so both modes behave correctly regardless of how granularly terms were originally assigned.

## Changelog

All notable changes to this project will be documented in the [CHANGELOG.md](CHANGELOG.md).

## License

This plugin is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
