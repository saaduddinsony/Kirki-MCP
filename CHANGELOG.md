# Changelog

## 0.2.9

- Stored titles that Kirki prints into an id or a section-link href lose only `"`, `'`, `` ` ``, `<`, and `>`. Tabs and newlines become a space. Unicode and the rest of the title stay. The read filter, the saved post meta, unpublished stages, and symbols all get that pass, including a heading a link points at. A later save no longer leaves that heading dirty.
- `audit-unsafe-titles` reports sections, headings, and other section-link targets, read from the raw stored meta.
- A symbol that was already re-keyed still has a section link pointing at `root` updated to `root-{post id}`.
- `duplicate-page` copies an allow-list of page meta: SEO, custom code, disabled symbols, variable mode, used fonts, and the page template.
- A Kirki symbol is not treated as ours unless its root style is exactly the rule this plugin writes.
- The migration takes a lock and re-reads each row before writing, so an editor save in between is hardened instead of overwritten.

## 0.2.8

- The upgrade migration reads post meta with the output filter removed, then writes the hardened hrefs. Unpublished Kirki stages are updated the same way, so a deactivated plugin and Kirki's own reader see the stored fix.
- Symbols this plugin built in 0.2.5 and earlier are detected by the `kirkiMcp` marker, the `kirki_mcp_symbol` meta, or the root style this plugin writes. Their root element id becomes `root-{post id}`, and section links that pointed at `root` are updated. A title stamped by 0.2.6 is not treated as proof, so symbols built in Kirki's editor stay as they are.
- The read filter only encodes link and image URLs. It does not rewrite section titles or anchors, so "Who We're" and non-Latin titles stay intact.
- Read-only abilities do not run the migration. `duplicate-page` copies every `kirki_*` meta key and the page template. The link filter caches its result for the request. The symbol class refresh takes an atomic lock. Raw class ids must be text in the allowed charset.

## 0.2.7

- Link URLs are percent-encoded for `' " ` < >` and whitespace. `esc_url_raw` keeps apostrophes, and Kirki prints link-text hrefs inside single quotes. Stored `kirki` meta is hardened on read, and a one-time rewrite runs on activation, an admin upgrade, or an authorized ability call.
- A section link can only point at a section or an element with an anchor. Titles that Kirki turns into that href go through `safe_label` on write and when the target is renamed.
- Form names go through `safe_label`. Kirki prints them with `wp_json_encode` and no HTML escaping.
- Each symbol root element id is `root-{post id}`. Kirki caches section HTML ids by element id for the whole request, so two symbols whose root id was `root` shared one HTML id. Plugin-built symbols are migrated on activation, upgrade, or an authorized write. Symbols created in Kirki's editor are skipped unless an update names them. The done flag is autoloaded and is set even if a record fails.
- `duplicate-page` inserts the copy as a draft. Slugs use `sanitize_title`, so non-ASCII titles are kept the way WordPress keeps them.
- A symbol update is validated before the post is renamed. Symbol anchors are checked against page anchors. `symbol_id` must be a Kirki symbol. Form-field classes are applied, or rejected if they were not resolved.
- Updating a global class refreshes symbol copies 20 at a time, header and footer first. Kirki's own class screen does not fire a hook, so those edits are copied on the next plugin class save or `reapply-global-classes`.
- Meta writes go through `wp_slash`, so backslashes survive `update_post_meta`.
- `tests/e2e/staging-check.php` runs only when `KIRKI_MCP_STAGING_CHECK` is set, then deletes its page and restores the previous header.
- Global classes and variables need Kirki builder access. `edit_theme_options` is not required.

## 0.2.6

- `duplicate-page` always creates a draft, even when the source page is published. Optional `title` and `slug` are sanitized, and the slug is made unique.
- Global classes on navigation items, inline links, and other nested lists are resolved. A class that is not found is an error, not a silent skip.
- `reapply-global-classes` accepts `symbol_id` without `page_id`.
- A symbol root HTML id includes the symbol post id, so a header and footer cannot both render `id="makerspace-header"`. An explicit anchor that another symbol already uses is rejected. Symbols already saved are updated once on the next request.
- Updating a global class still refreshes the frozen copy on every `kirki_symbol` post, including header and footer symbols rendered on pages.

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
