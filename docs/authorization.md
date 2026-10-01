---
title: Authorization
---

# Authorization

The admin calls the engine's services in-process, so it does not pass through the REST layer's `ecommerce.can`
middleware. It enforces the engine's abilities itself:

- **Entering the admin.** Every admin route runs the `ecommerce-admin.access` middleware, which lets a user in only when
  at least one navigation entry is visible to them. The middleware is also registered as Livewire persistent middleware,
  so update requests from an admin page are re-checked.
- **Each screen.** A screen's Livewire component authorizes its ability in `mount()`.
- **Each action.** Every action authorizes again before it runs, because a Livewire action can be called directly with a
  forged request.

A denial always ends in a 403.

## How an ability is decided

Abilities are `{resource}.{action}`. The engine's policies delegate to `EcommerceAuthorizer`, which checks, in order:

1. the Gate ability `ecommerce.{resource}.{action}`, when the host defines it;
2. otherwise the umbrella `ecommerce.admin` gate, when the host defines it;
3. then the `ap.ecommerce.abilities.{resource}.{action}` filter.

The default is deny. The simplest setup is the umbrella gate:

```php
Gate::define( 'ecommerce.admin', fn ( $user ) => $user->is_admin );
```

A product whose type comes from a satellite that is not installed always renders read-only, with the engine's warning
banner (`<x-artisanpack-ec-product-type-warning :product="$product" />`). This applies even to a full admin.

## With cms-framework

When `artisanpack-ui/cms-framework` is installed, the admin:

- registers one RBAC permission per ability, with the slug `ecommerce.{resource}.{action}`;
- creates a `shop-manager` role that holds all of them.

Permissions are registered:

- by `php artisan ecommerce-admin:install`;
- by `php artisan ecommerce-admin:sync-permissions`;
- after every `php artisan migrate`.

A user holding a permission is granted the matching ability through the engine's ability filter. The filter only ever
adds access, so an `ecommerce.admin` gate keeps working alongside RBAC.

## Abilities per screen

| Screen | Route name (`artisanpack.ecommerce.admin.…`) | Screen ability | Action abilities |
|---|---|---|---|
| Dashboard | `dashboard` | any visible screen | — |
| Orders | `orders.index`, `orders.show` | `order.viewAny`, `order.view` | `order.update`, `order.edit-fulfilled`, `order.cancel`, `order.refund`, `refund.view`, `refund.create` |
| Reviews | `reviews.index` | `review.viewAny` | `review.view`, `review.moderate`, `review.delete` |
| Products | `products.index`, `products.create`, `products.edit` | `product.viewAny` | `product.view`, `product.create`, `product.update`, `product.delete` |
| Categories, tags | `categories.index`, `tags.index` | `product.viewAny` | `product.create`, `product.update`, `product.delete` |
| Inventory | `inventory.index` | `inventory.viewAny`¹ | `inventory.adjust`¹ |
| Digital files | `digital-files.index` | `digitalFile.viewAny` | `digitalFile.create`, `digitalFile.update`, `digitalFile.delete` |
| License keys | `license-keys.index` | `licenseKey.view` | `licenseKey.revoke` |
| Customers | `customers.index`, `customers.show` | `customer.viewAny`, `customer.view` | `customer.update`, `customer.delete` |
| Promotions | `promotions.index`, `promotions.create`, `promotions.edit` | `promotion.viewAny` | `promotion.*`, `coupon.*` |
| Reports | `reports.show` | `report.view`² | — |
| Shipping | `shipping.index` | `shippingZone.viewAny` | `shippingZone.create`, `shippingZone.update`, `shippingZone.delete` |
| Tax | `tax.index` | `taxRate.viewAny` | `taxRate.create`, `taxRate.update`, `taxRate.delete` |
| Notifications | `notifications.index`, `notifications.edit` | `notificationTemplate.viewAny` | `notificationTemplate.view`, `notificationTemplate.update` |
| Webhooks | `webhooks.index`, `webhooks.show` | `webhookSubscription.viewAny` | `webhookSubscription.create`, `webhookSubscription.update`, `webhookSubscription.delete` |
| Order statuses | `order-statuses.index` | `orderSubstatus.viewAny`² | `orderSubstatus.create`, `orderSubstatus.update`, `orderSubstatus.delete` |
| Kanban boards | `kanban-boards.index`, `kanban-boards.edit` | `kanbanBoard.viewAny` | `kanbanBoard.*` |
| Settings | `settings.show` | `settings.view`² | `settings.update` |

¹ Until the engine ships the inventory abilities (engine issue #148), `inventory.viewAny` checks `product.viewAny` and
`inventory.adjust` checks `product.update`.

² The engine does not define these abilities yet (engine issue #148). Until it does, they resolve through the umbrella
`ecommerce.admin` gate and the `ap.ecommerce.abilities.*` filter. A host may also define the Gate ability directly,
e.g. `ecommerce.report.view`.

Screens appear in the navigation only once they have shipped.
