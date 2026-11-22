# Build a WooCommerce product page with Kirki MCP

This workflow uses [Kirki WooCommerce Bridge](https://github.com/saaduddinsony/kirki-woocommerce-bridge) 1.0.0 through the `kirki-mcp/woo-*` abilities. The official MCP Adapter still exposes only its three meta-tools; call `mcp-adapter-execute-ability` with `ability_name` set to the `kirki-mcp/…` name below.

Requirements on the WordPress site: WooCommerce, Kirki 6.4.x, and Kirki WooCommerce Bridge. Kirki Pro and Kirki eCommerce may also be active; they are detected separately and are not required for these steps.

## 1. Confirm the Bridge is ready

1. Call `kirki-mcp/get-site-context`.
2. Read `kirki_woocommerce_bridge`. It should report `installed: true` and `ready: true`. If the Bridge is missing, install it, then retry. The object never includes license keys or secrets.
3. Optionally call `kirki-mcp/woo-get-status` for versions, features, and element counts.

## 2. See which contexts and elements you can use

1. `kirki-mcp/woo-list-template-contexts` — WooCommerce page contexts (`single_product`, `shop`, `cart`, `checkout`, and the rest) and the conditions each supports.
2. `kirki-mcp/woo-list-elements` with optional `group` (`product`, `shop`, `cart`, `account`, `general`).
3. `kirki-mcp/woo-get-element` with `name` set to an element such as `kirki-woo-product-title` or `product-title`.

Template **rules** decide which Kirki design is used for a WooCommerce context. **Designs** are Kirki posts (`kirki_template` or a Kirki-edited page).

## 3. Create a starter product design

1. `kirki-mcp/woo-create-design` with `{ "context": "single_product", "starter": true }`. By default this also creates an assigning rule (`create_rule: true`).
2. Note the returned design id (and rule id, if a rule was created).
3. Optionally `kirki-mcp/woo-add-design-elements` to insert more elements under `parent_id` (default `body`).
4. Optionally `kirki-mcp/woo-update-template` to change the rule's conditions or priority.
5. `kirki-mcp/woo-resolve-template` with `{ "context": "single_product", "object_id": PRODUCT_ID }` to dry-run which rule wins.

## 4. Preview an element and bind dynamic data

1. `kirki-mcp/woo-render-element` with `{ "name": "product-title", "product_id": PRODUCT_ID }` returns the same HTML the frontend uses.
2. `kirki-mcp/woo-build-block` returns a validated Kirki node you can insert with the usual page tools (it does not save by itself).
3. `kirki-mcp/woo-list-dynamic-fields`, then `kirki-mcp/woo-resolve-dynamic-field` with `{ "field": "name", "scope": "product", "product_id": PRODUCT_ID }`.
4. In Kirki, bind a field as `dynamicContent: { "type": "kirki_woo_product", "value": "<field>" }`.

## 5. Settings, listing, and deletes

- `kirki-mcp/woo-get-settings` / `kirki-mcp/woo-update-settings` change Bridge options. Unknown keys are rejected.
- `kirki-mcp/woo-list-templates` and `kirki-mcp/woo-list-designs` inspect existing rules and designs.
- `kirki-mcp/woo-delete-template` removes a rule and keeps the design. `kirki-mcp/woo-delete-design` trashes (or `force: true` deletes) a `kirki_template` design and its rules; pages are never deleted. Both require `confirm: true`.

Element and render tools need the `edit_posts` capability. Settings, rules, and designs need `manage_woocommerce`.
