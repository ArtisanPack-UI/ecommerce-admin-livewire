---
title: Accessibility
---

# Accessibility

The admin targets **WCAG 2.2 AA**. Every flow is meant to work by keyboard and with a screen reader. This page says what is in place, what is known not to meet the target yet, and how to report a problem.

## What is in place

- **Keyboard reordering.** Every reorderable list (rule-builder rows, variants, attributes, gallery images, categories, sub-statuses, kanban columns and widgets, shipping methods) has move up and move down buttons beside the drag handle. After a move, focus stays on the same button on the moved item (or the other button when the item reaches the top or bottom), and screen readers hear "Moved {item} to position N of M."
- **Focus return.** Cancel, Save, and Confirm in inline confirmations and edit rows send focus back to the control that opened them, instead of dropping it to the top of the page.
- **Error summaries.** A failed save shows a summary at the top of the form ("Not saved. Fix the 3 highlighted fields.") with `role="alert"`, so it is announced. Each field shows its own message.
- **Status in text, not colour.** Order, payment, fulfillment, review, and shipment statuses are badges with text labels. Report changes against the previous period are written out in words.
- **Tables.** Every table has a caption, header cells with `scope`, and `aria-sort` on sortable columns. Row checkboxes are labelled with the row they select.
- **Live regions.** Real-time updates, result counts, and reorder messages are announced through polite live regions.
- **Charts.** Report charts are labelled and have the data table below them as their text equivalent. The dashboard sparkline has a text summary.
- **Navigation.** A "Skip to content" link, a real button to open the navigation drawer on small screens, navigation badges with text descriptions ("7 low-stock items"), and `aria-keyshortcuts` on the command palette button.
- **Linked products.** The upsell, cross-sell, and related lists on the product form reorder by keyboard the same way.
- **Busy controls.** Move and remove buttons are disabled while their request runs, so a double press can't move or remove the wrong row.
- **Unsaved changes.** The product, promotion, notification template, kanban board, and settings forms ask before you leave with unsaved changes.
- **Format errors.** Money and percent fields announce a format error and point to it with `aria-describedby` while the value is invalid.
- **New tabs.** Links that open a new tab (tracking links, review photos) say so to screen readers.
- **Colour contrast warnings.** With `artisanpack-ui/accessibility` installed, the sub-status and kanban column editors warn when a chosen colour fails WCAG AA contrast.
- **Automated checks.** The browser test suite runs axe on every admin screen against a seeded demo store. Any violation fails the build, except the known library issues listed below.

## Known limitations

These come from `artisanpack-ui/livewire-ui-components` markup the admin cannot change. They are tracked in [livewire-ui-components#117](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/117) and [livewire-ui-components#119](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/119).

- **Tabs.** Tab controls are not reachable by keyboard: they have no `href` or `tabindex` and no arrow-key support, which affects the product, promotion, and customer screens. The tab content wrapper has `role="tablist"` with no tabs inside, and inactive tab labels fall below 4.5:1 contrast. With daisyUI newer than 5.0.x, tab panels render empty (#119).
- **Modals and drawers.** Dialog semantics (labelling, focus containment) are incomplete in places.
- **Field errors.** Some error messages are not programmatically associated with their field.
- **Select and textarea names.** `x-artisanpack-select` and `x-artisanpack-textarea` label with a `<legend>`, so the control itself has no accessible name.
- **Menu roles.** The navigation menu gives links `role="menuitem"` without a `role="menu"` parent and puts non-`<li>` children in its list.
- **Low-contrast secondary text.** Header subtitles and stat titles use a 50% text colour (about 3.3:1).
- **Pagination.** A disabled "Previous" control carries `aria-label` on a `<span>`.
- **Single-select pickers.** livewire-ui-components 2.1.0's single-select `choices` reads its selection's length even while hidden, so a picker whose value starts empty (`null`) logs a console error. It works normally otherwise; the product form's linked-product pickers avoid it.
- **Code editor.** The Ace editor used for notification templates captures the Tab key, so keyboard focus cannot leave it, and leaves its input unlabelled.

Each is excluded from the automated check by rule and selector in `tests/Browser/Support/Accessibility.php` (`LIBRARY_ISSUES`), so nothing else can regress unnoticed. An exclusion is removed when the library fixes it.

## Reporting an issue

Open an issue on the [GitHub repository](https://github.com/ArtisanPack-UI/ecommerce-admin-livewire/issues) with:

- the screen and what you were doing;
- the assistive technology, browser, and operating system;
- what you expected and what happened.
