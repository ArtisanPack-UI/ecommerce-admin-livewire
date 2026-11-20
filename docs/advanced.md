---
title: Advanced
menu_order: 80
---

# Advanced

How the admin works inside: the request flow, its middleware, and how it keeps destructive actions from running twice. The Artisan commands are in the [reference](reference/commands.md). Running the package's own test suites is covered in `CONTRIBUTING.md`.

## How a request flows

```text
Route → AdminScreenController (returns a page view)
          → page view  @extends( $ecommerceAdminLayout )
              → <livewire:artisanpack-ecommerce-admin-… />
                   → engine policy check
                   → engine service        (writes)
                   → query class on models (reads)
```

- **Pages are controller actions** returning a view that embeds one Livewire component. They are not full-page Livewire routes, so `route:cache` works and the layout can be swapped.
- **Writes go through engine services.** A component never assembles a multi-table write itself.
- **Reads use per-screen query classes** in `src/Queries/`, with eager loading, so filters and sorting are tested without rendering.

## Middleware

| Alias | Class | Does |
| --- | --- | --- |
| `ecommerce-admin.access` | `EnsureAdminAccess` | Lets a user in only when at least one navigation entry is visible to them |
| `ecommerce-admin.throttle` | `ThrottleAdminMutations` | Runs Livewire update requests through the engine's `ecommerce.admin.mutate` limiter (120 a minute per user by default). Page loads are not counted. |

Both run on every admin route after `admin.middleware`, and both are registered as Livewire persistent middleware so update requests from an admin page re-run them. Over the limit, the throttle answers 429 with `Retry-After` and a JSON `message`; the page turns it into a toast. Change the limit by redefining the engine's `ecommerce.admin.mutate` rate limiter.

## Idempotency

Refunds, cancellations, shipments, order edits, and confirmed bulk actions carry a one-time action token minted when the form renders. The token is consumed in the same transaction as the write, so a double click or a replayed request runs once.

## Registering routes yourself

Set `admin.routes_enabled` to `false` and register the routes in your own route file. Keep:

- the route names under `artisanpack.ecommerce.admin.` (the navigation, links, and palette look them up);
- the `ecommerce-admin.access` and `ecommerce-admin.throttle` middleware;
- `AdminScreenController` actions, so pages get their layout.

`routes/admin.php` in the package is the reference.
