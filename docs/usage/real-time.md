---
title: Real-Time Updates
---

# Real-Time Updates

With real-time updates on, the dashboard and order screens react to changes made elsewhere: a new paid order, a status change, a stock adjustment, a new review. They are off by default. Without them every screen works the same, but only shows changes on reload.

## Requirements

All of these must be true for a user's screens to subscribe:

1. `realtime.enabled` is `true` in this package's config.
2. The engine broadcasts admin events: `artisanpack.ecommerce.graphql.subscriptions` is `true`. The engine then re-broadcasts order, payment, stock, and review events on the private channel `ecommerce.admin` and registers its authorization.
3. `rebing/graphql-laravel` is installed. The engine's GraphQL support (and with it the admin broadcasts) is optional, so install it yourself: `composer require rebing/graphql-laravel`.
4. Laravel broadcasting is configured with a broadcaster, for example [Reverb](https://laravel.com/docs/reverb), and **Laravel Echo** is set up on `window.Echo` in your `resources/js/app.js`.
5. The user passes the channel's check: they hold `order.viewAny`, `product.viewAny`, **and** `webhookSubscription.viewAny`.

If any of these is missing, no listener is registered and the page behaves as if Echo were absent. Nothing errors.

## Setup

```bash
php artisan install:broadcasting   # installs Reverb and Echo
```

```php
// config/artisanpack/ecommerce-admin-livewire.php
'realtime' => [
    'enabled' => true,
],
```

Then turn on the engine's subscriptions flag (`artisanpack.ecommerce.graphql.subscriptions`) in the engine's config and rebuild your assets.

The screens use Livewire's `echo-private:` listeners on `private-ecommerce.admin`. The engine names its events with `broadcastAs()`, so the listeners use the leading-dot form, for example `echo-private:ecommerce.admin,.orderStatusChanged`.

## What updates

| Screen | Listens for | What happens |
| --- | --- | --- |
| Dashboard | `orderStatusChanged`, `paymentSucceeded`, `stockChanged`, `reviewSubmitted` | Re-renders the widgets and announces what changed |
| Orders index | `orderStatusChanged`, `paymentSucceeded` | Re-renders the list and shows "N new orders since you opened this page" with a Dismiss button |
| Order detail | `orderStatusChanged`, `paymentSucceeded` for this order | Shows "This order was changed elsewhere" with a **Reload order** button. The page does not replace what you are looking at until you reload. |

The order detail screen re-reads the order from the database before deciding anything. It never trusts the broadcast payload.

Every update is also announced in a polite live region, so screen-reader users hear it without losing their place.

## Troubleshooting

If nothing updates:

- Check both config flags. The admin's flag alone does nothing.
- Check `rebing/graphql-laravel` is installed. Without it the screens never subscribe, even with both flags on.
- Check that `window.Echo` exists in the browser console on an admin page.
- Check the user holds all three abilities above. A user without `webhookSubscription.viewAny` never subscribes.
- Check the broadcaster is running (`php artisan reverb:start`) and the queue worker is processing broadcast jobs.
