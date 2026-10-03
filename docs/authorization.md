---
title: Authorization
---

# Authorization

The admin calls the engine's services in-process, so it does not pass through the REST layer's `ecommerce.can`
middleware. It enforces the engine's abilities itself:

- **Entering the admin.** Every admin route runs the `ecommerce-admin.access` middleware, which lets a user in only when
  at least one navigation entry is visible to them. The middleware is also registered as Livewire persistent middleware,
  so update requests from an admin page are re-checked.
- **Each screen.** A screen's Livewire component authorizes its ability in `mount()`, and again on every update request.
- **Each action.** Every action authorizes again before it runs, because a Livewire action can be called directly with a
  forged request.

A denial always ends in a 403. The default is deny: with no gate defined, every screen answers 403.

## How an ability is decided

Abilities are `{resource}.{action}`. The engine's policies delegate to `EcommerceAuthorizer`, which checks, in order:

1. the Gate ability `ecommerce.{resource}.{action}`, when the host defines it;
2. otherwise the umbrella `ecommerce.admin` gate, when the host defines it;
3. then the `ap.ecommerce.abilities.{resource}.{action}` filter.

The simplest setup is the umbrella gate:

```php
Gate::define( 'ecommerce.admin', fn ( $user ) => $user->is_admin );
```

For finer control, define individual abilities. A Gate ability receives the record when there is one, so it can check
ownership:

```php
Gate::define( 'ecommerce.order.viewAny', fn ( $user ) => $user->hasRole( 'fulfillment' ) );
Gate::define( 'ecommerce.order.view', fn ( $user, $order = null ) => $user->hasRole( 'fulfillment' ) );
Gate::define( 'ecommerce.order.update', fn ( $user, $order = null ) => $user->hasRole( 'fulfillment' ) );
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
adds access, so an `ecommerce.admin` gate keeps working alongside RBAC. When you define the
`ecommerce.{resource}.{action}` gate yourself, your gate's answer stands and the permission is not consulted for it.

## Two-factor authentication

Neither the engine nor the admin applies 2FA. Add your 2FA middleware to `admin.middleware`. See
[Installation](Installation).

## Every ability

These are the abilities registered as RBAC permissions (prefixed `ecommerce.`).

| Resource | Actions |
| --- | --- |
| `product` | `viewAny`, `view`, `create`, `update`, `delete`, `restore` |
| `order` | `viewAny`, `view`, `create`, `update`, `edit-fulfilled`, `cancel`, `refund` |
| `refund` | `create`, `view` |
| `customer` | `viewAny`, `view`, `update`, `delete` |
| `promotion` | `viewAny`, `view`, `create`, `update`, `delete` |
| `coupon` | `create`, `update`, `delete` |
| `taxRate` | `viewAny`, `create`, `update`, `delete` |
| `shippingZone` | `viewAny`, `create`, `update`, `delete` |
| `kanbanBoard` | `viewAny`, `view`, `create`, `update`, `delete` |
| `kanbanCard` | `move` |
| `notificationTemplate` | `viewAny`, `view`, `update` |
| `webhookSubscription` | `viewAny`, `create`, `update`, `delete` |
| `digitalFile` | `viewAny`, `create`, `update`, `delete` |
| `licenseKey` | `view`, `revoke` |
| `review` | `viewAny`, `view`, `moderate`, `delete` |
| `inventory` | `viewAny`, `adjust` |
| `orderSubstatus` | `viewAny`, `create`, `update`, `delete` |
| `settings` | `view`, `update` |
| `report` | `view` |

Inventory has its own abilities, separate from `product.*`, so staff can count and adjust stock without editing
products. `product.viewAny` and `product.update` do not grant them.

The admin issues refunds under `order.refund`. `refund.create` and `refund.view` are used by the engine's REST API, not
by the admin. Coupons have no view abilities of their own: they are shown inside their promotion, so the command
palette's coupon results need `promotion.viewAny` and `view` on the coupon's promotion.

## Abilities per screen

| Screen | Route name (`artisanpack.ecommerce.admin.…`) | To open it |
| --- | --- | --- |
| Dashboard | `dashboard` | Any visible screen. Each widget and figure has its own ability; see [Dashboard](Usage-Dashboard). |
| Orders | `orders.index` | `order.viewAny` |
| Order detail | `orders.show` | `order.view` on the order |
| Reviews | `reviews.index` | `review.viewAny` |
| Products | `products.index` | `product.viewAny` |
| New product | `products.create` | `product.create` |
| Edit product | `products.edit` | `product.view` on the product (read-only without `product.update`) |
| Product import | `products.import` | `product.create` |
| Categories | `categories.index` | `product.viewAny` |
| Tags | `tags.index` | `product.viewAny` |
| Inventory | `inventory.index` | `inventory.viewAny` |
| Digital files | `digital-files.index` | `digitalFile.viewAny` |
| License keys | `license-keys.index` | `licenseKey.view` or `licenseKey.revoke`¹ |
| Customers | `customers.index` | `customer.viewAny` |
| Customer detail | `customers.show` | `customer.view` on the customer |
| Promotions | `promotions.index` | `promotion.viewAny` |
| New promotion | `promotions.create` | `promotion.create` |
| Edit promotion | `promotions.edit` | `promotion.view` on the promotion (read-only without `promotion.update`) |
| Shipping | `shipping.index` | `shippingZone.viewAny` |
| Tax | `tax.index` | `taxRate.viewAny` |
| Notifications | `notifications.index` | `notificationTemplate.viewAny` |
| Edit notification | `notifications.edit` | `notificationTemplate.view` |
| Webhooks | `webhooks.index`, `webhooks.show` | `webhookSubscription.viewAny` |
| Order statuses | `order-statuses.index` | `orderSubstatus.viewAny` |
| Kanban boards | `kanban-boards.index` | `kanbanBoard.viewAny` |
| Edit kanban board | `kanban-boards.edit` | `kanbanBoard.view` on the board |
| Reports | `reports.show` | `report.view` |
| Settings | `settings.show` | `settings.view` |

¹ A user with only `licenseKey.revoke` can open the screen, but sees each key masked to its last group and cannot
search by key. Keys are shown in full only with `licenseKey.view`.

Screens appear in the navigation only for users who can open them.

## Abilities per action

Viewing actions on every index screen (search, filter, sort, paginate, select rows, export the selection as CSV) need
only the screen's ability, as do Cancel and Close buttons. The tables below list everything else. Method names are the
Livewire actions, for reference when you test or extend.

A bulk action is shown only to users who hold its ability, and is checked again when it runs.

### Orders

| Action | Method | Ability |
| --- | --- | --- |
| Bulk: change sub-status | `runBulkAction( 'change-substatus' )` | `order.update` on each order |
| Change status or sub-status | `changeStatus`, `changeSubstatus` | `order.update` |
| Cancel the order | `cancelOrder` | `order.cancel` |
| Issue a refund | `startRefund`, `refund` | `order.refund` |
| Create a shipment | `startShipment`, `createShipment` | `order.update` |
| Edit tracking | `editTracking`, `updateTracking` | `order.update` |
| Buy a shipping label | `buyLabel` | `order.update` |
| Edit the order (draft, preview, apply, roll back) | `startEdit`, `addItem`, `removeNewItem`, `previewEdit`, `applyEdit`, `rollbackEdit` | `order.update`; also `order.edit-fulfilled` once fulfillment has started |
| Search products while editing | `searchPicker` | `product.viewAny` |
| Add or remove a board assignment | `addToBoard`, `removeFromBoard` | `kanbanCard.move` |
| Add a note | `addNote` | `order.update` |
| Delete a note | `deleteNote` | The note's author, or `order.update` |
| Load more timeline entries | `loadMore` | `order.view` |
| Reload after a real-time change | `reloadOrder` | `order.view` |

License keys on the order are shown in full only with `licenseKey.view`.

### Reviews

| Action | Method | Ability |
| --- | --- | --- |
| Show a review | `showReview` | `review.view` |
| Approve, mark as spam, requeue | `moderate` | `review.moderate` |
| Reject with a reason | `startReject`, `reject` | `review.moderate` |
| Delete | `confirmDelete` | `review.delete` |
| Bulk: approve, reject, mark as spam, back to pending | `runBulkAction` | `review.moderate` |
| Bulk: delete | `runBulkAction( 'delete' )` | `review.delete` |

### Products

| Action | Method | Ability |
| --- | --- | --- |
| Save a new product | `save` | `product.create` |
| Save an existing product | `save` | `product.update` |
| Edit prices, gallery, featured image, and the type panel before saving | `addScheduledPrice`, `removePrice`, `addGalleryUrl`, `removeGalleryImage`, `moveGalleryImage`, `clearFeaturedImage`, `mediaSelected`, panel actions | `product.create` (new) or `product.view` (existing, and the form must not be read-only). Nothing is stored until Save. |
| Search categories, tags, or child products | `searchPicker` | `product.viewAny` |
| Export catalog CSV | `exportCatalog`, bulk `export-catalog` | `product.viewAny` |
| Bulk: publish, archive, add or remove a category or tag | `runBulkAction` | `product.update` |
| Bulk: delete | `runBulkAction( 'delete' )` | `product.delete` |

### Product import

| Action | Method | Ability |
| --- | --- | --- |
| Upload, map columns, check, apply, resume, discard | `upload`, `editMapping`, `check`, `apply`, `resume`, `discard` | `product.create` |
| Download failed rows or the sample file | `downloadErrors`, `downloadSample` | `product.create` |
| Each imported row | — | `product.create` (new) or `product.update` (existing), for the user who started the import |

### Categories and tags

| Action | Method | Ability |
| --- | --- | --- |
| Create a category | `create`, `save` | `product.create` |
| Edit or reorder a category | `edit`, `save`, `move` | `product.update` |
| Delete a category | `confirmDelete`, `delete` | `product.delete` |
| Create a tag | `createTag` | `product.create` |
| Rename a tag | `startRename`, `saveRename` | `product.update` |
| Delete a tag | `confirmDelete`, bulk `delete` | `product.delete` |
| Bulk: merge into a tag | `runBulkAction( 'merge' )` | `product.update` |

### Inventory

| Action | Method | Ability |
| --- | --- | --- |
| Adjust stock | `startAdjust`, `adjust` | `inventory.adjust` |
| Edit the low-stock threshold | `editThreshold`, `saveThreshold` | `inventory.adjust` |
| Toggle backorders | `toggleBackorder` | `inventory.adjust` |
| Bulk adjust from CSV | `openBulkAdjust`, `dryRunBulkAdjust`, `applyBulkAdjust` | `inventory.adjust` |

### Digital files and license keys

| Action | Method | Ability |
| --- | --- | --- |
| Create a digital file | `create`, `save` | `digitalFile.create` |
| Edit a digital file | `edit`, `save` | `digitalFile.update` |
| Delete a digital file | `confirmDelete`, bulk `delete` | `digitalFile.delete` |
| Search products for a file | `searchPicker` | `product.viewAny` |
| Show a key's activations | `showActivations` | `licenseKey.view` |
| Revoke a key | `startRevoke`, `revoke` | `licenseKey.revoke` |

### Customers

| Action | Method | Ability |
| --- | --- | --- |
| Edit details | `startEdit`, `saveDetails` | `customer.update` |
| Delete and anonymize | `startDelete`, `deleteCustomer` | `customer.delete` |
| Add, edit, or delete an address; set a default | `startAdd`, `startEdit`, `saveAddress`, `makeDefault`, `confirmDelete`, `deleteAddress` | `customer.update` |
| Save notification preferences | `savePreferences` | `customer.update` |
| Add a note | `addNote` | `customer.update` |
| Delete a note | `deleteNote` | The note's author, or `customer.update` |
| See the Orders tab | — | `order.viewAny` (the tab is hidden without it) |

### Promotions and coupons

| Action | Method | Ability |
| --- | --- | --- |
| Save a new promotion | `save` | `promotion.create` |
| Save an existing promotion | `save` | `promotion.update` |
| Edit rule-builder rows | `addRule`, `removeRule`, `moveRule`, `reorderRules`, `toggleRule` | `promotion.create` (new) or `promotion.update` (existing) |
| Search products in a rule | `searchPicker` | `product.viewAny` |
| Bulk: switch on, switch off | `runBulkAction` | `promotion.update` |
| Bulk: delete | `runBulkAction( 'delete' )` | `promotion.delete` |
| Add or generate codes | `addCode`, `generateCodes` | `coupon.create` |
| Rename a code | `startRename`, `saveRename` | `coupon.update` |
| Delete a code | `confirmDelete`, `deleteCode` | `coupon.delete` |
| Export codes; see the Coupons and Usage tabs | `exportCodes` | `promotion.view` |

### Shipping and tax

| Action | Method | Ability |
| --- | --- | --- |
| Create a zone | `createZone`, `saveZone` | `shippingZone.create` |
| Edit a zone | `editZone`, `saveZone` | `shippingZone.update` |
| Delete a zone | `confirmDeleteZone`, `delete` | `shippingZone.delete` |
| Add, edit, reorder, or delete a method | `createMethod`, `editMethod`, `saveMethod`, `moveMethod`, `confirmDeleteMethod` | `shippingZone.update` |
| Create a tax rate or class | `createRate`, `saveRate`, `createClass` | `taxRate.create` |
| Edit a tax rate or class | `editRate`, `saveRate`, `editClass`, `saveClass` | `taxRate.update` |
| Delete a tax rate or class | `confirmDeleteRate`, `confirmDeleteClass`, `deleteClass`, bulk `delete` | `taxRate.delete` |
| Bulk: switch rates on or off | `runBulkAction` | `taxRate.update` |
| Import rates from CSV | `openImport`, `checkImport`, `applyImport` | `taxRate.create`; rows that update an existing rate also need `taxRate.update` |
| Export rates, download the sample | `exportRates`, `downloadSample` | `taxRate.viewAny` |

### Notifications and webhooks

| Action | Method | Ability |
| --- | --- | --- |
| Save a template | `save` | `notificationTemplate.update` |
| Reset to default | `confirmReset`, `resetToDefault` | `notificationTemplate.update` |
| Add a locale | `addLocale` | `notificationTemplate.update` |
| Send a test | `sendTest` | `notificationTemplate.update` |
| Create a subscription | `create`, `save` | `webhookSubscription.create` |
| Edit a subscription | `edit`, `save` | `webhookSubscription.update` |
| Delete a subscription | `confirmDelete`, `delete` | `webhookSubscription.delete` |
| Rotate the secret | `confirmRotate`, `rotateSecret` | `webhookSubscription.update` |
| Re-enable a disabled subscription | `reEnable` | `webhookSubscription.update` |
| Inspect a delivery | `openDelivery` | `webhookSubscription.viewAny` |
| Replay a delivery | `replay` | `webhookSubscription.update` |

### Order statuses and kanban boards

| Action | Method | Ability |
| --- | --- | --- |
| Create a sub-status | `create`, `save` | `orderSubstatus.create` |
| Edit or reorder a sub-status | `edit`, `save`, `move` | `orderSubstatus.update` |
| Delete a sub-status | `confirmDelete`, `delete` | `orderSubstatus.delete` |
| Create a board | `create`, `save` | `kanbanBoard.create` |
| Make a board the default, reorder boards | `makeDefault`, `move` | `kanbanBoard.update` |
| Delete a board | `confirmDelete`, `delete` | `kanbanBoard.delete` |
| Edit a board, its routing rules, columns, and automations | `save`, rule, column, and automation actions | `kanbanBoard.update` |
| Search products in a rule | `searchPicker` | `product.viewAny` |

### Reports and settings

| Action | Method | Ability |
| --- | --- | --- |
| Export a report as CSV | `export` | `report.view` |
| Save settings, reset a value, confirm a base-currency change | `save`, `resetToDefault`, `confirmBaseCurrencyChange`, `addMapRow`, `removeMapRow` | `settings.update` |

## How this is tested

`tests/Feature/Authorization/AuthorizationMatrixTest.php` declares, for every registered Livewire component, the
ability its `mount()` needs and the ability each public action needs. The suite proves each one both ways: a user who
can enter the admin but lacks the ability is refused, and a user holding just that ability gets through. A component or
action missing from the matrix fails the suite, so a new screen cannot ship without declaring its authorization.
