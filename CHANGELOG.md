# Changelog

## 0.2.5

- Select and radio option values must match letters, numbers, spaces, and `. , : @ + / -`. Labels have quotes and markup stored as HTML entities, because Kirki prints both unescaped.
- Radio fields keep `required` on the group. Input ids are prefixed with the form element id, so two forms can share a field name.
- Updating a global class refreshes the class snapshot stored on every symbol that uses it.

## 0.2.4

- Publishing a page no longer leaves an older unpublished Kirki revision as the open stage. The next edit starts from the content that was just published, and publishing again does not restore the older revision.
- Navigation is not put into Kirki's closed hamburger state, so the menu stays visible at every width.
- A symbol stores a copy of each global class its elements use, which is what Kirki reads when it renders a header or footer.
- `reapply-global-classes` accepts a symbol id, or a page id that is actually a symbol.
- Forms include a configurable success message and error message. Fields can be select, checkbox, radio, or url, and labels point at their inputs.

## 0.2.3

- Font stylesheet output escapes `localUrl` as well as `fontUrl`. `&#038;` in a stored URL is decoded back to `&` before escaping, so a later read does not keep the HTML entity.
- Every page save and every symbol save slugs stored section titles and symbol names, including `update-symbol` when no new name is sent and `duplicate-page`.
- `audit-unsafe-titles` lists pages and symbols whose stored titles still fail that rule. It does not change them.
- A key on a form field is not reserved. Explicit anchors are rejected when they match an id Kirki would assign by numbering repeated titles, such as `card-1`.
- Accented Latin titles are transliterated (`Café` stays `Cafe`). A title with no Latin letters becomes `section-N`.
- A `<style>` tag may only carry a `media` value made of letters, numbers, and CSS media punctuation, and that value is escaped.

## 0.2.2

- Section titles and symbol names keep only letters, numbers, spaces, dots, underscores, and hyphens. Kirki prints those titles as HTML ids without escaping.
- Repeated section titles are allowed, because Kirki numbers the ids. Explicit anchors are rejected when they match after slugging, so "Our Work" and our-work are the same id.
- Keys on inline links and navigation items are reserved before those elements are compiled.
- Head code returns an error when markup follows a style block. A CSS `>` combinator and `<style media>` are allowed. `</style` and `@import` are not.
- Font stylesheet URLs already stored in Kirki's custom fonts are passed through `esc_url` before Kirki prints them.

## 0.2.1

- Custom code requires `unfiltered_html` plus Kirki builder access. Head code is limited to sanitized `<style>` blocks. Body code goes through `wp_kses_post`.
- Font URLs are built from the family name, or rejected when they contain quotes, markup, or whitespace.
- Every form includes a submit button, and a form can receive another button later.
- Attaching a global class removes that element's default local CSS. `reapply-global-classes` does the same for pages built earlier.
- Duplicate anchors are rejected. Link text hrefs can be edited. SEO text cannot contain shortcodes, and the Open Graph key is always present so Kirki does not warn.
- An element that has a global class no longer gets Kirki's default local declarations, which were painting over the class. Styles set on that element are marked important so they still override the class.
- Pages are marked to use the current class manager, so Kirki includes those classes in the front-end CSS.
- Setting a font variable registers each family with Kirki's custom fonts as a `fonts.googleapis.com` stylesheet. Kirki prints the link tag.
- A section `key` or `anchor` is stored as the element title, which is what Kirki turns into the HTML id. Other elements get the same id attribute.
- `list` renders a `ul` or `ol`. `hide_on` hides an element on `md`, `tablet`, or `mobile`. `update-custom-code` writes the head and body code Kirki prints. Unknown element types name the supported list.

## 0.2.0

- Published pages are edited on an unpublished Kirki stage. `publish-page` copies that stage onto the live post meta before Kirki records the version, so a later publish no longer keeps the old content.
- Header and footer roles are reassigned only after the new symbol validates and saves.
- `get-symbol` returns the symbol's own tree.
- Page graphs without `root` and `body` are rejected instead of being replaced with a blank page. Saves check parent and child links and reject cycles.
- Permissions follow Kirki's administrator and editor role map, plus the matching WordPress capability. Editors can change global classes and variables. Other roles with `edit_pages` cannot.
- CSS declarations cannot contain braces or `@` rules. Variant keys must be a real viewport plus `hover`, `active`, `focus`, `before`, `after`, or `placeholder`.
- Font stacks are quoted per family. `register-font` stores a `fonts.googleapis.com` stylesheet in Kirki's custom fonts. Text styles can be saved as variables.
- `update-element` ignores raw class ids and only accepts class names. Non-Kirki pages are not converted unless `convert` is true.
- New elements for the Makerspace build: `navigation` with a mobile menu, `form` with email delivery, `link-text`, and inline links inside text. `update-page-settings` sets the meta description.
- Supported Kirki range defaults to 6.4.x. `get-site-context` stays available and explains a mismatch. The `kirki_mcp_supported_kirki_range` filter can widen it.
- Duplicating a page uses Kirki's duplicate, then copies SEO and variable-mode meta.

## 0.1.0

First working release.

- Companion plugin that registers Kirki abilities with the WordPress Abilities API and relies on the official MCP Adapter for HTTP and stdio clients.
- Create, read, update, duplicate, publish, and trash Kirki pages.
- Add, edit, restyle, and remove sections and elements. The compiler writes the element map and CSS style blocks Kirki 6.4 stores.
- Global classes, colors, font families, and sizes.
- Symbols, including a single site header and footer.
- List existing Kirki templates.
- Capability checks, CSS and text sanitizing, and an explicit `confirm` flag for removal and trash.
- Tested against WordPress 7.1.4 and Kirki 6.4.2.
- Symbols are created through Kirki's SymbolManager. Kirki 6.4.2 has no symbol write route under `kirki/v1`; the ajax symbol endpoints need a browser nonce.
- Image upload goes through WordPress `POST /wp/v2/media`, which accepts an application password. Kirki's own upload does not.
- Page saves do not call `Template::save_template_data()`, because that writes published meta with staging left off.
- Symbol and page meta that is not JSON-shaped is rejected. The plugin does not unserialize agent input.
