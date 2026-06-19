# AGENTS.md

Local context for `cms/resources/js/`.

## Golden References

- `alpine.js`
- `alpine/form-modal.js`
- `alpine/tree-select.js`
- `alpine/manual-sort.js`

## Checklist

1. Register every new Alpine module in `alpine.js`.
2. Treat Blade `data-*` attributes as a contract. Update both JS and Blade together.
3. Keep package globals rare and intentional. `form-modal.js` is the pattern for shared globals.
4. Preserve opt-in behavior when a feature is optional. See the passkeys comment in `alpine.js`.
5. Rebuild affected consumers after JS changes.

## Anti-Patterns

- Adding a controller file without importing it in `alpine.js`.
- Renaming DOM hooks in JS only.
- Depending on consumer-specific markup from shared Alpine modules.
