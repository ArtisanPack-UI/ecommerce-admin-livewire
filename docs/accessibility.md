---
title: Accessibility
menu_order: 45
---

# Accessibility

The admin targets **WCAG 2.2 AA**. Every flow is meant to work by keyboard and with a screen reader. This page says what is in place, what is known not to meet the target yet, and how to report a problem.

## What is in place

- **Keyboard reordering.** Every reorderable list (rule-builder rows, variants, attributes, gallery images, categories, sub-statuses, kanban columns and widgets, shipping methods) has move up and move down buttons beside the drag handle. After a move, focus stays on the same button on the moved item (or the other button when the item reaches the top or bottom), and screen readers hear "Moved {item} to position N of M."
- **Focus return.** Cancel, Save, and Confirm in inline confirmations and edit rows send focus back to the control that opened them, instead of dropping it to the top of the page.
- **Error summaries.** A failed save shows a summary at the top of the form ("Not saved. Fix the 3 highlighted fields.") with `role="alert"`. It takes focus, so it's announced, and links to each field. Each field shows its own message.
- **Status in text, not color.** Order, payment, fulfillment, review, and shipment statuses are badges with text labels. Report changes against the previous period are written out in words.
- **Tables.** Every table has a caption, header cells with `scope`, and `aria-sort` on sortable columns. Row checkboxes are labeled with the row they select.
- **Live regions.** Real-time updates, result counts, and reorder messages are announced through polite live regions.
- **Charts.** Report charts are captioned by a text summary, have focusable columns with tooltips, and have the data table below them as their text equivalent. Numbers use the app locale. The dashboard sparkline is hidden from screen readers, and its caption carries the data.
- **Navigation.** A "Skip to content" link, a navigation drawer on phones that traps focus and returns it to the menu button, links whose names include their counts ("Inventory (7 low-stock items)"), and `aria-keyshortcuts` on the command palette button.
- **Linked products.** The upsell, cross-sell, and related lists on the product form reorder by keyboard the same way.
- **Busy controls.** Move and remove buttons are disabled while their request runs, so a double press can't move or remove the wrong row.
- **Unsaved changes.** The product, promotion, notification template, kanban board, and settings forms ask before you leave with unsaved changes.
- **Format errors.** Money and percent fields announce a format error and point to it with `aria-describedby` while the value is invalid. The currency code and "%" are read after the label ("Price, USD").
- **New tabs.** Links that open a new tab (tracking links, review photos) say so to screen readers.
- **Right to left.** Right-to-left locales get `dir="rtl"` in the standalone layout. Keys, SKUs, URLs, amounts, and code stay left to right inside it.
- **Color contrast warnings.** The sub-status and kanban column editors warn when a chosen color's icon and dot would fall below the 3:1 non-text contrast WCAG 1.4.11 asks for, against the light or the dark surface.
- **Automated checks.** The browser test suite runs axe on every admin screen against a seeded demo store. Any violation fails the build.

## Known limitations

The browser suite runs axe on every screen with no exclusions: `LIBRARY_ISSUES` in `tests/Browser/Support/Accessibility.php` is empty. If a `livewire-ui-components` bug ever needs one again, the entry must match only that component's markup (never a generic element such as `fieldset`) and be listed here with the component it comes from.

What the automated check does not cover:

- **Initial state only.** axe checks each screen as it first renders. Content that appears after an action (open drawers and dialogs, tabs other than the first, config-form repeater rows, the bulk-action bar) isn't checked by axe. The keyboard browser tests cover focus and announcements in some of it, not its full markup.
- **Not a screen-reader test.** axe catches missing names, roles, and contrast. It doesn't prove the reading order, the wording of announcements, or that a flow makes sense by ear. Those are checked by hand.

## Reporting an issue

Open an issue on the [GitHub repository](https://github.com/ArtisanPack-UI/ecommerce-admin-livewire/issues) with:

- the screen and what you were doing;
- the assistive technology, browser, and operating system;
- what you expected and what happened.
