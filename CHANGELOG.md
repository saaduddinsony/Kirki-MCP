# Changelog

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
