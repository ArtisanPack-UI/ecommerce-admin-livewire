---
title: Usage Overview
---

# Usage

How the admin's screens work for the staff who use them, and the shared behaviour every screen follows.

## In this section

- [Screens](Usage-Screens) - Every screen, its URL, route name, and what it does
- [Dashboard](Usage-Dashboard) - Key figures, widgets, and the first-run state
- [Command Palette](Usage-Command-Palette) - Cmd+K search and actions
- [Real-Time Updates](Usage-Real-Time) - Live order and stock updates over Laravel Echo
- [Product CSV Import and Export](Usage-Product-Csv) - The catalog CSV format and the import flow

## Behaviour every screen shares

- **Index screens** have search, filters, sortable columns, pagination, row selection, bulk actions, and CSV export of the selection. Search, filters, sort, page size, and page live in the URL, so a filtered list can be bookmarked or shared.
- **Bulk actions** only show when the user holds their ability. Destructive ones ask for confirmation first.
- **Forms** validate every field on save. A failed save shows an error summary at the top of the form, announced to screen readers, and marks each field.
- **Destructive and money-moving actions** (refunds, cancellations, shipments, order edits, bulk deletes) carry a one-time token, so a double click or a replayed request runs once. Their buttons disable while the request runs.
- **Rate limiting.** Changes made from the admin go through the engine's `ecommerce.admin.mutate` limiter (120 a minute per user by default). Over the limit, the action is refused and a toast says how long to wait. Page loads are not counted.
- **Read-only views.** A user who can view a record but not update it sees the form read-only. A product whose type comes from a satellite that is not installed is always read-only, with a warning banner.
- **Money** is formatted with the engine's `MoneyFormatter` and entered in major units (12.50). It is stored as integer minor units, with no float arithmetic. Dates use the engine's `LocalizedDate`.
- **Activity and notes.** Orders, products, customers, and promotions have an activity timeline. Orders and customers have internal notes. Order notes can be marked visible to the customer (off by default). A note can be deleted by its author or by a user who can update the record.
