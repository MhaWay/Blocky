=== Blockwork Engine ===
Contributors: mhaway
Tags: page builder, blocky, tailwind, builder, editor
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The engine of Blockwork: rendering pipeline, closed-set block registry, three-layer CSS compiler and REST API.

== Description ==

Blockwork Engine is the rendering engine behind the Blockwork builder: a closed-set block registry,
a deterministic CSS compiler (Tailwind vocabulary, static hashed stylesheets, no inline CSS)
and a REST document API. It must be installed together with the Blockwork Builder plugin.

* 90+ blocks as declarative descriptors (no bespoke inspector code per block)
* Closed-set controls only: arbitrary CSS is possible solely through an explicit code-mode channel
* Frontend CSS is always a static, hashed stylesheet; system fonts by default
* Entrance animations, overlays, actions and interactions with zero page-level inline styles
* Respects prefers-reduced-motion and works with JavaScript disabled
* Fully translatable (text domain: blocky)

== Installation ==

1. Upload the blocky-core folder to /wp-content/plugins/.
2. Activate through the Plugins screen.
3. Activate Blockwork Builder as well.

== Frequently Asked Questions ==

= Does Blockwork use Google Fonts? =
No. System font stacks by default; fonts can be self-hosted.

= Does it modify my content when converting a page? =
No. Conversion only adds a document; original block content is never touched.

== Changelog ==

= 0.1.0 =
* Initial public preview: engine, compiler, block library, REST API, i18n catalogs.
