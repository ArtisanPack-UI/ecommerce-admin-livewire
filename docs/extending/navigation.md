---
title: Navigation
---

# Navigation

`ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav` is the single source of the admin navigation. The standalone sidebar, the `cms-framework` menu, the command palette's "Go to" actions, and the admin access check all read it.

## Entries

An entry has the shape of the engine's `AdminMenuRegistry` entries:

| Key | Required | Meaning |
| --- | --- | --- |
| `key` | Yes | Unique key. A later entry with the same key replaces an earlier one. |
| `label` | Yes | Visible label |
| `route` | Yes | Full route name. The entry is hidden while the route is not registered. |
| `parameters` | No | Route parameters |
| `section` | No | Section key. Unknown sections fall back to the top section. |
| `position` | No | Order within the section (default `100`) |
| `icon` | No | Heroicon name (default `o-squares-2x2`) |
| `permission` | No | `{resource}.{action}` ability (`ecommerce.` prefix allowed). Hidden without it. `null` shows it to anyone who can enter the admin, but never grants access on its own. |
| `badge` | No | Badge key; see below |

An entry is visible when the user holds its permission and its route exists. A user may enter the admin when they hold the permission of at least one entry, so adding an entry with a new permission also lets holders of that permission in.

## Add an entry

Use the `ap.ecommerceAdminLivewire.nav.items` filter. It receives the core entries plus any from the engine's `AdminMenuRegistry`, and returns the list:

```php
addFilter( 'ap.ecommerceAdminLivewire.nav.items', function ( array $items ): array {
    $items[] = [
        'key'        => 'loyalty',
        'section'    => 'customers',
        'label'      => __( 'Loyalty points' ),
        'icon'       => 'o-gift',
        'route'      => 'loyalty.admin.index',
        'position'   => 20,
        'permission' => 'customer.viewAny',
        'badge'      => 'loyalty-pending',
    ];

    return $items;
} );
```

When the engine's `AdminMenuRegistry` is bound, its entries are merged in before the filter runs. Until then, the filter is the way to add entries.

## Add a section

Sections are keyed arrays of `label` and `position`. Add one with `ap.ecommerceAdminLivewire.nav.sections`:

```php
addFilter( 'ap.ecommerceAdminLivewire.nav.sections', function ( array $sections ): array {
    $sections['subscriptions'] = [ 'label' => __( 'Subscriptions' ), 'position' => 35 ];

    return $sections;
} );
```

| Core section | Position |
| --- | --- |
| `top` (no label; the Dashboard) | 0 |
| `orders` | 10 |
| `catalog` | 20 |
| `customers` | 30 |
| `marketing` | 40 |
| `reports` | 50 |
| `configuration` | 60 |

Empty sections are not shown.

## Badges

A badge shows a count next to an entry. The core badge keys are `orders-awaiting-fulfillment`, `pending-reviews`, and `low-stock`. Each is one count query cached for 60 seconds.

Answer your own badge key with `ap.ecommerceAdminLivewire.nav.badge`. It receives `null` and the badge key, and returns a count. Zero or `null` hides the badge:

```php
addFilter( 'ap.ecommerceAdminLivewire.nav.badge', function ( ?int $count, string $badge ): ?int {
    if ( 'loyalty-pending' !== $badge ) {
        return $count;
    }

    return cache()->remember( 'loyalty.pending-count', 60, fn (): int => LoyaltyClaim::query()->pending()->count() );
} );
```

Give screen-reader users a meaningful description with `ap.ecommerceAdminLivewire.nav.badgeLabel`. It receives the default text (":count items"), the badge key, and the count:

```php
addFilter( 'ap.ecommerceAdminLivewire.nav.badgeLabel', function ( string $label, string $badge, int $count ): string {
    if ( 'loyalty-pending' !== $badge ) {
        return $label;
    }

    return trans_choice( ':count claim to review|:count claims to review', $count, [ 'count' => $count ] );
} );
```

Keep badge queries cheap and cached: they run on every admin page.

## In the cms-framework menu

With `cms-framework` installed and `admin.auto_register_cms_nav` on, every visible entry is added to the CMS menu with the slug `ecommerce-{key}`, and every section becomes a CMS section with the slug `ecommerce-{section}`. See [Layouts](Installation-Layouts).
