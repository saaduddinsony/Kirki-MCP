# Feature coverage matrix (Kirki MCP 0.5.0)

This matrix compares what an agent can do through Kirki MCP against:

- Free [Kirki](https://kirki.com) 6.4.3 (visual builder **and** the Customizer toolkit in the same plugin)
- [Kirki Pro](https://kirki.com) 6.4.3 (thin add-on that swaps in the Pro editor script, a License submenu, and access to official CloudFront catalogs; it is not a second copy of the builder)
- [Kirki eCommerce](https://wordpress.org/plugins/kirki-ecommerce/) 1.0.2 (standalone Themeum plugin, slug `kirki-ecommerce`; it does **not** require the Kirki builder)
- [Kirki WooCommerce Bridge](https://github.com/saaduddinsony/kirki-woocommerce-bridge) 1.0.0 (slug `kirki-woocommerce-bridge`; requires WooCommerce 8.0+ and Kirki 6.4.x)

Sources used for the 0.5.0 audit: Bridge 1.0.0 (`docs/API.md`, `KirkiWoo\Operations::all()`), plus the 0.4.0 sources: Kirki 6.4.3 (wordpress.org), Kirki Pro 6.4.3 (`kirki-pro/`, installed locally, no license key captured), kirki-ecommerce 1.0.2 (wordpress.org + plugin source, including `routes/api.php`), and the 0.3.0 ability surface. Pro is not bundled or redistributed.

Legend: **Supported** is available through an ability in 0.5.0 (0.4.0 columns keep the previous snapshot). **Partial** is available with documented limits. **Withheld** is intentionally blocked. **Gap** would mean a remaining unimplemented product feature; none of the 0.3.0 product gaps remain except the withheld payment-webhook and password-change surfaces and version gating.

Runtime detection:

- Builder abilities stay gated by `Compatibility::supported()` (Kirki `>= 6.4.0` and `< 6.5.0` by default).
- Customizer, settings, registry, audit, eCommerce, and WooCommerce Bridge abilities still register when the builder is missing or is an unsupported Kirki version.
- `kirki-mcp/get-site-context` reports `kirki_pro` (boolean), `kirki_pro_plugin` (`installed`, `version`, `licensed`), `kirki_ecommerce` status, `kirki_woocommerce_bridge` status, and `store_payment_writes`. The license key is never returned.
- eCommerce tools return a clear error when `kirki-ecommerce` is not installed. Payment and checkout writes also need `kirki_mcp_allow_store_payment_writes`.
- WooCommerce Bridge tools return a clear error when the Bridge is not installed. Element and render tools need `edit_posts`; settings, rules, and designs need `manage_woocommerce`. `woo-delete-template` and `woo-delete-design` need `confirm: true`.

## Visual builder (free Kirki 6.4.x)

| Feature | Edition | 0.3.0 | 0.4.0 | Notes |
| --- | --- | --- | --- | --- |
| List / get / create / update / publish / trash / duplicate pages | Free | Supported | Supported | Unchanged |
| Sections and common elements | Free | Supported | Supported | See `get-element-schema` |
| Video, slider, and tabs | Free | Supported | Supported | Unchanged from 0.3.0 |
| Popups (`kirki_popup`) | Free post type; Pro advertises fuller UI | Supported | Supported | Unchanged |
| Content Manager collections and items | Free REST `/kirki/v1/content-manager/…` | Supported | Supported | Unchanged |
| Global classes, variables, symbols, media, fonts, custom code, SEO | Free | Supported | Supported | Unchanged |
| Header / footer symbols | Free | Supported | Supported | |
| List existing templates (`kirki_template`) | Free | Supported | Supported | `list-templates` |
| Export a site pack | Free (`Kirki\ExportImport\TemplateExport`) | Gap | Supported | `export-site-template` then `process-template-export` |
| Export a single page as `kirki-data.json` zip | Free data format | Gap | Supported | `export-page-template` writes to `uploads/kirki-mcp/` |
| Import a template ZIP | Free site pack / page pack | Gap | Supported | `preview-template-zip`, `import-template-zip`, `process-template-import`. Requires `confirm: true`. Page packs create a draft unless `page_id` is set. Published overwrite still requires confirm. Does not call `Template::save_template_data()` on a published page without that confirm path; new pages go through `Page_Repository::save` |
| Dynamic content bindings | Free renderer + Pro editor (`properties.dynamicContent`) | Gap | Supported | `list-dynamic-content-fields`, `set-dynamic-content`. Types/sources are allowlisted |
| Interaction triggers and action lists | Free data + Pro editor (`properties.interactions`) | Gap | Supported | `set-interactions`. Events are allowlisted |
| Interaction / animation timeline library | Pro editor (`properties.interactionLibrary`) | Gap | Supported | `set-interaction-library` |

## Customizer toolkit (free Kirki)

Responsive, tabs, input slider, headlines/dividers, and margin/padding controls were merged into free Kirki at 5.0.0. They are not a separate Pro product in 6.4.x.

| Feature | Edition | 0.3.0 | 0.4.0 | Notes |
| --- | --- | --- | --- | --- |
| Read / write `theme_mods` | WordPress core + Kirki fields | Supported | Supported | Secret keys blocked |
| Read / write options | WordPress core + Kirki `option` fields | Partial | Partial | Same key policy. New unknown option names still cannot be invented outside the MCP registry |
| List registered Kirki fields, panels, and sections | Free (PHP-registered) | Partial | Partial | `list-customizer-fields` still reads PHP-registered fields |
| Register new panels, sections, or controls at runtime | MCP-owned registry, applied on `init` | Gap | Supported | `list-customizer-registry`, `register-customizer-panel`, `register-customizer-section`, `register-customizer-field`, `unregister-customizer-item`. Persisted in `kirki_mcp_customizer_registry` and re-applied via `\Kirki::add_config` / `add_panel` / `add_section` / `add_field` (config id `kirki_mcp`). Does not rewrite theme PHP. Control types are allowlisted from free 6.4 Field classes. Unregister requires `confirm: true` |
| List / publish Customizer changesets | WordPress core | Supported / Partial | Supported / Partial | `publish-changeset` still may not reproduce every Customizer UI side effect |
| Export / import a settings dump | MCP | Gap | Supported | `export-settings`, `import-settings` (confirm required). Secret keys omitted or rejected |

## Kirki Pro detection and catalogs

| Feature | Edition | 0.3.0 | 0.4.0 | Notes |
| --- | --- | --- | --- | --- |
| Detect the Pro plugin vs a valid license | Pro | Partial | Supported | `kirki_pro_plugin.installed` / `version` is the add-on being loaded (`KIRKI_PRO_VERSION`, `KirkiProMain`). `licensed` / `kirki_pro` is `\Kirki\HelperFunctions::is_pro_user()`. The key is never returned |
| List official premium templates | Pro catalog (CloudFront `templates.json`, publicly fetchable metadata) | Gap | Supported | `list-premium-templates`. Metadata only; assets are not redistributed |
| List official pre-built sections | Pro catalog (`/pre-built/…/all.json` and `all-dark.json`) | Gap | Supported | `list-prebuilt-templates` |
| Import a catalog item | Official CDN zip | Gap | Supported | `apply-catalog-template`. Requires `confirm: true`. If the first download fails, a stored site license is appended in memory (`license_key`, `host`) and is never returned. A valid Pro license may be required for some zips; 0.4.0 was tested without a captured key |
| Exercise Pro-only editor chrome | Pro editor JS | Partial | Partial | Data format (dynamic content, interactions, catalogs) is covered. Extra Pro UI chrome that is editor-only is not cloned |

## Kirki eCommerce 1.0.2

Standalone plugin: [wordpress.org/plugins/kirki-ecommerce](https://wordpress.org/plugins/kirki-ecommerce/). REST namespace `kirki/ecommerce/v1`. Admin routes in the 1.0.2 source are behind Auth + Admin (`manage_options`).

Abilities: `ecommerce-status`, `ecommerce-get`, `ecommerce-mutate`, `set-store-payment-writes`, `list-audit-log`. DELETE requires `confirm: true`. `dry_run: true` previews a call without hitting REST. Secret fields are redacted on the way out and are never written to the audit log.

| Feature | 0.3.0 | 0.4.0 | Route coverage |
| --- | --- | --- | --- |
| Installation / version status | Supported | Supported | Local detection |
| Products, variants, duplicate, catalog taxonomy, attributes | Supported | Supported | Including bulk routes and attribute value batch/bulk |
| Orders, activities, refunds, calculate | Partial | Supported | Refunds and activity DELETE are allowlisted. DELETE still needs `confirm: true` |
| Coupons | Supported | Supported | Including bulk |
| Customers | Partial | Supported | Including DELETE and bulk. Password-change stays withheld |
| Currencies and exchange sync | Partial | Supported | Create/update/delete and `/currencies/bulk` |
| Tax and shipping profiles, methods, boxes | Supported | Supported | Including bulk |
| Store settings and email templates | Partial | Supported | Preview, test-mail, and restore are allowlisted |
| Dashboard, analytics, onboarding, sample data, setup checklist, pages/fix | Partial | Supported | `/onboarding`, `/onboarding/sample-data`, `/home/first-sale`, `/pages/fix` |
| WooCommerce migration | Supported | Supported | Unchanged |
| Storefront cart, checkout, payment methods | Gap | Supported, gated | Reads work. Writes need `set-store-payment-writes` (`kirki_mcp_allow_store_payment_writes`, default off). `dry_run` works without the opt-in. Credentials are write-only and redacted on read |
| Online/offline payment gateway install, update, uninstall, credential PUT | Gap | Supported, gated | Same opt-in, redaction, dry-run, and audit as cart/checkout |
| Incoming payment webhooks | Gap | Withheld | `/payment/webhook/{provider_id}` stays blocked (forging a webhook can mark orders paid) |
| Customer password change | Gap | Withheld | `/password-change` stays blocked |
| Customer profile, addresses, wishlist, resend-verification | Gap | Supported | Allowlisted except password-change |

## Kirki WooCommerce Bridge 1.0.0

Public plugin: [saaduddinsony/kirki-woocommerce-bridge](https://github.com/saaduddinsony/kirki-woocommerce-bridge). REST namespace `kirki-woo/v1`. Official operations are defined once in `KirkiWoo\Operations::all()` and also registered as `kirki-woo/*` abilities. Kirki MCP proxies every official operation one-to-one as `kirki-mcp/woo-<operation>`.

A typical product-page flow is in `examples/workflows/woocommerce-bridge.md`.

| Feature | 0.4.0 | 0.5.0 | Ability |
| --- | --- | --- | --- |
| Detect installation, version, readiness, and dependencies | Gap | Supported | `woo-get-status` (also `get-site-context.kirki_woocommerce_bridge`) |
| List / get WooCommerce builder elements and schemas | Gap | Supported | `woo-list-elements`, `woo-get-element` |
| Preview-render an element and build an insertable Kirki node | Gap | Supported | `woo-render-element`, `woo-build-block` |
| List and resolve product / store dynamic fields | Gap | Supported | `woo-list-dynamic-fields`, `woo-resolve-dynamic-field` |
| Get and update Bridge settings | Gap | Supported | `woo-get-settings`, `woo-update-settings` |
| List contexts, list / create / get / update / resolve / delete template rules | Gap | Supported | `woo-list-template-contexts`, `woo-list-templates`, `woo-create-template`, `woo-get-template`, `woo-update-template`, `woo-resolve-template`, `woo-delete-template` (confirm required) |
| List / create / get designs, add elements, delete designs | Gap | Supported | `woo-list-designs`, `woo-create-design`, `woo-get-design`, `woo-add-design-elements`, `woo-delete-design` (confirm required) |
| Coexist with Kirki eCommerce on the same site | Gap | Supported | Detection only; eCommerce and Bridge stay separate APIs |

The official 21 operations are the entire public Bridge surface in 1.0.0. MCP does not invent extra WooCommerce operations.

## What 0.5.0 still does not do, and why

1. **Incoming payment webhooks** — intentionally withheld. A webhook payload can mark an order paid.
2. **Customer / WordPress password-change** — intentionally withheld, even for “complete” checkout coverage.
3. **Kirki versions other than 6.4.x** — builder internals are version-gated. Widen with `kirki_mcp_supported_kirki_range` only after verifying a newer build.
4. **Premium CDN zips when the site has no stored license** — catalog JSON is public; some asset downloads may require a valid Kirki Pro license on the site. 0.4.0 retries with a stored key in memory and never returns it. This was tested without activating a license.
5. **Theme PHP Customizer registration** — the MCP registry is a persisted, MCP-owned layer. It does not rewrite a theme’s PHP.
6. **Amazon Q CLI HTTP Basic and Goose `streamable_http` Basic headers** — not shown in official vendor docs, so those clients get a verified stdio-bridge recipe instead of a guessed header field. Trae’s official HTTP `headers` schema is shipped.
