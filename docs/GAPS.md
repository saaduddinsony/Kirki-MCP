# Feature coverage matrix (Kirki MCP 0.3.0)

This matrix compares what an agent can do through Kirki MCP against:

- Free [Kirki](https://kirki.com) 6.4.2 (visual builder **and** the Customizer toolkit in the same plugin)
- [Kirki Pro](https://kirki.com) (separate license; advertised as dynamic content, a full interaction timeline, popup builder, and premium templates)
- [Kirki eCommerce](https://wordpress.org/plugins/kirki-ecommerce/) 1.0.2 (standalone Themeum plugin, slug `kirki-ecommerce`; it does **not** require the Kirki builder)

Sources used for the audit: the Kirki 6.4.2 plugin source, the wordpress.org listing and SVN/tag zip for kirki-ecommerce 1.0.2 (`https://plugins.svn.wordpress.org/kirki-ecommerce/`, `https://downloads.wordpress.org/plugin/kirki-ecommerce.1.0.2.zip`), and the Kirki MCP 0.2.9 abilities. Pro 8.x PHP was not available and is not bundled.

Legend: **Supported** is available through an ability in 0.3.0. **Partial** is available with documented limits. **Gap** is still not implemented, or is intentionally withheld.

Runtime detection:

- Builder abilities stay gated by `Compatibility::supported()` (Kirki `>= 6.4.0` and `< 6.5.0` by default).
- Customizer and eCommerce abilities still register when the builder is missing or is an unsupported Kirki version.
- `kirki-mcp/get-site-context` always reports `kirki_pro` (boolean only; the license key is never returned), `kirki_ecommerce` status, and `builder_supported`.
- eCommerce tools return a clear error when `kirki-ecommerce` is not installed.

## Visual builder (free Kirki 6.4.x)

| Feature | Edition | 0.2.9 | 0.3.0 | Notes |
| --- | --- | --- | --- | --- |
| List / get / create / update / publish / trash / duplicate pages | Free | Supported | Supported | `list-pages`, `get-page`, `create-page`, `update-page`, `publish-page`, `trash-page`, `duplicate-page` |
| Sections and common elements (heading, paragraph, text, button, image, link, div, navigation, form, list) | Free | Supported | Supported | See `get-element-schema` |
| Video elements | Free | Gap | Supported | New compiler type `video` (`src` https URL) |
| Sliders | Free (renderer + data format exist in 6.4.2) | Gap | Supported | Compiler type `slider` with `slides[]`. Advanced autoplay/timeline options advertised with Pro are not generated |
| Tabs | Free (renderer names `tab_menu`, `tab`, `tab_pane` exist in 6.4.2) | Gap | Supported | Compiler type `tabs` with at least two titled tabs |
| Popups (`kirki_popup`) | Free post type / data format; Pro advertises a fuller popup builder | Gap | Supported | `list-popups`, `get-popup`, `create-popup`, `update-popup`, `trash-popup`. Pro-only UI chrome is not claimed |
| Content Manager collections and items | Free REST `/kirki/v1/content-manager/…` | Gap | Supported | `content-manager-get`, `content-manager-mutate`. Distinct from Kirki eCommerce collections. DELETE requires `confirm: true` |
| Global classes, variables, symbols, media, fonts, custom code, SEO | Free | Supported | Supported | Unchanged from 0.2.9 |
| Header / footer symbols | Free | Supported | Supported | |
| List existing templates (`kirki_template`) | Free | Supported | Supported | `list-templates` |
| Import / apply a template ZIP | Free + Pro premium templates | Gap | Gap | ZIP import can overwrite published page meta. Left unimplemented on purpose until a confirm-and-sandbox design exists |
| Full interaction timeline | Advertised Pro; some interaction fields exist in free data | Gap | Gap | Format is not fully documented without Pro editor JS. Not invented |
| Dynamic content bindings | Advertised Pro; some `dynamicContent` keys exist in free PHP | Gap | Gap | Same reason: do not invent a writer for an under-specified Pro format |
| Premium / marketplace templates | Pro | Gap | Gap | Requires Pro assets that this plugin must not redistribute |

## Customizer toolkit (free Kirki; formerly-Pro controls merged in 5.0.0)

Responsive, tabs, input slider, headlines/dividers, and margin/padding controls were merged into free Kirki at 5.0.0. They are not a separate Pro product in 6.4.2.

| Feature | Edition | 0.2.9 | 0.3.0 | Notes |
| --- | --- | --- | --- | --- |
| Read / write `theme_mods` | WordPress core + Kirki fields | Gap | Supported | `list-theme-mods`, `get-theme-mod`, `set-theme-mod` (`edit_theme_options`) |
| Read / write options | WordPress core + Kirki `option` fields | Gap | Partial | `get-option`, `set-option`. Blocked: `siteurl`, `home`, `admin_email`, salts, passwords, secrets, `license_key`, `kirki_ecommerce_payment*`. New unknown option names cannot be created |
| List registered Kirki fields, panels, and sections | Free (PHP-registered) | Gap | Partial | `list-customizer-fields` reads `\Kirki\Compatibility\Kirki::$fields` / `\Kirki::$fields` when present |
| Register new panels, sections, or controls at runtime | Theme / plugin PHP | Gap | Gap | Field *registration* is PHP in the theme or a plugin. This agent can write values; it does not invent new Customizer controls |
| List Customizer changesets | WordPress core | Gap | Supported | `list-changesets` |
| Publish a Customizer changeset | WordPress core | Gap | Partial | `publish-changeset` publishes the `customize_changeset` post. The Customizer UI has additional publish side effects this call may not fully reproduce. Requires `confirm: true` |
| Export / import a full settings dump | n/a | Gap | Gap | No first-class Kirki export format is written here. Prefer theme_mod / option abilities for individual values |

## Kirki Pro detection

| Feature | Edition | 0.2.9 | 0.3.0 | Notes |
| --- | --- | --- | --- | --- |
| Detect a valid Pro license | Pro | Gap | Supported | `Compatibility::is_pro_user()` calls `\Kirki\HelperFunctions::is_pro_user()` when that class exists, otherwise returns `false`. `get-site-context` includes `kirki_pro` as a boolean only |
| Exercise Pro-only editor features | Pro | Gap | Partial | Popups, sliders, and tabs can be written in the free 6.4.2 data format. Dynamic content, the full interaction timeline, and premium template packs remain gaps. Pro source is not bundled |

## Kirki eCommerce 1.0.2

Standalone plugin: [wordpress.org/plugins/kirki-ecommerce](https://wordpress.org/plugins/kirki-ecommerce/). REST namespace `kirki/ecommerce/v1`. Admin routes in the 1.0.2 source are behind Auth + Admin (`manage_options`). Products live in custom tables, not WooCommerce or WP product post types.

Abilities: `ecommerce-status`, `ecommerce-get`, `ecommerce-mutate`. All require `manage_options`. `ecommerce-get` / `ecommerce-mutate` proxy only allowlisted routes via `rest_do_request` as the current user. DELETE requires `confirm: true`. When the plugin is absent, status is honest and mutate/get throw a clear “not installed” error rather than a generic REST failure.

| Feature | 0.2.9 | 0.3.0 | Route coverage |
| --- | --- | --- | --- |
| Installation / version status | Gap | Supported | No REST; local detection of `KIRKI_ECOMMERCE_VERSION` / `Kirki\Ecommerce\App\KirkiEcommerce` |
| Products, variants, duplicate | Gap | Supported | `/products`, `/product-variants`, `/variants`, `/products/{id}/duplicate` |
| Categories, brands, tags, eCom collections, attributes and values | Gap | Supported | Matching admin CRUD routes |
| Orders (list, get, create, update, actions) | Gap | Supported | `/orders`, `/orders/{id}`, `/edit`, `/action`, `/shipping-carriers`, `/calculate/order`. Order DELETE allowed with `confirm` |
| Coupons | Gap | Supported | Including generate-new-code, validate, action, and DELETE with `confirm` |
| Customers | Gap | Partial | GET/POST/PUT. Customer DELETE is not allowlisted |
| Currencies | Gap | Partial | GET only (create/update/delete currency records is a remaining gap) |
| Tax profiles, shipping profiles, methods, boxes | Gap | Supported | Reads plus create/update; profile and box DELETE with `confirm` |
| Payment method list (read) | Gap | Partial | GET `/payment-methods`, `/online-payments`, `/offline-payments`. Credential writes, plugin install/update/uninstall, and enable patches are **not** exposed |
| Store settings | Gap | Partial | GET `/settings/{key}`, PUT `/settings`. Email test-mail / restore routes are not exposed |
| Dashboard and analytics | Gap | Supported | `/dashboard/overview`, `/dashboard/attention`, `/analytics/metrics/{key}`, `/analytics/regions`, `/setup-checklist`, `/app-config`, `/pages`, `/countries`, `/onboarding/defaults` |
| WooCommerce migration | Gap | Supported | `/migration/sources`, `/migration/scan`, `/migration/runs`, `/migration/runs/latest`, `/migration/runs/{id}`, `/migration/runs/{id}/chunk` (verified in `app/Migration/routes.php`) |
| Storefront cart and checkout | Gap | Gap | Intentionally withheld. Checkout charges a real payment method; cart is a customer session, not a merchant admin tool |
| Payment webhooks | Gap | Gap | Intentionally withheld (`/payment/webhook/{provider_id}`) |
| Online payment gateway install / update / uninstall / credential PUT | Gap | Gap | Intentionally withheld |
| Customer password change | Gap | Gap | Intentionally withheld |
| Sample-data onboarding import, bulk routes, refunds, email test sends | Gap | Gap | Not allowlisted in this release |

## What 0.3.0 still does not do, and why

1. **Template ZIP import** — can overwrite published Kirki meta. Needs a sandboxed, confirmable design.
2. **Runtime registration of new Customizer panels/controls** — those are PHP in the theme or a plugin, not a stored document this agent should invent.
3. **Pro dynamic content and the full interaction timeline** — advertised Pro features; writer format not verified without Pro editor source.
4. **Premium template packs** — Pro assets; this plugin will not redistribute them.
5. **eCommerce payments, webhooks, gateway installs, password changes, and checkout** — too easy to leak credentials or move real money. Status and catalog/order/migration work is the supported merchant surface.
6. **Kirki versions other than 6.4.x** — builder internals are version-gated. Widen with `kirki_mcp_supported_kirki_range` only after verifying a newer build.
7. **Amazon Q Developer, Trae, and Goose client recipes** — official configuration pages were not usable at documentation time. See `docs/CLIENTS.md`.
