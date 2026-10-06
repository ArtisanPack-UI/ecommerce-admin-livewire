---
title: Dashboard Widgets
---

# Dashboard Widgets

The dashboard renders a list of widgets from `ArtisanPackUI\EcommerceAdminLivewire\Support\DashboardWidgets`. Add, replace, or remove widgets with the `ap.ecommerceAdminLivewire.dashboard.widgets` filter.

## The filter

```php
applyFilters( 'ap.ecommerceAdminLivewire.dashboard.widgets', array $widgets, ?Authenticatable $user ): array
```

It receives the core widgets and the current user, and returns the list.

## Widget shape

| Key | Required | Meaning |
| --- | --- | --- |
| `key` | Yes | Unique key. A later widget with the same key replaces an earlier one. |
| `label` | No | Heading (defaults to the key) |
| `view` | One of these | A Blade view, rendered with `$widget` and `$dashboard` (the data the core widgets share) |
| `component` | One of these | A Livewire component name, mounted with no parameters |
| `permission` | No | `{resource}.{action}` ability (`ecommerce.` prefix allowed). Hidden without it. `null` shows it to everyone who can open the admin. A malformed value skips the widget and logs a warning. |
| `position` | No | Lower renders first (default `100`) |
| `width` | No | `full` (default) or `half`. Half-width widgets sit side by side on large screens. |

A widget with neither `view` nor `component` is dropped. When both are set, `view` wins.

## Core widgets

| Key | Position | Width | Permission |
| --- | --- | --- | --- |
| `kpis` | 10 | Full | None (each figure has its own) |
| `sales` | 20 | Half | `report.view` |
| `low-stock` | 30 | Half | `inventory.viewAny` |
| `recent-orders` | 40 | Full | `order.viewAny` |

## Add a widget

```php
addFilter( 'ap.ecommerceAdminLivewire.dashboard.widgets', function ( array $widgets ): array {
    $widgets[] = [
        'key'        => 'subscriptions-renewing',
        'label'      => __( 'Renewing this week' ),
        'component'  => 'subscriptions-renewing-widget',
        'permission' => 'order.viewAny',
        'position'   => 35,
        'width'      => 'half',
    ];

    return $widgets;
} );
```

Your Livewire component must authorize what it shows. The `permission` only hides the widget.

## Remove or replace a widget

```php
addFilter( 'ap.ecommerceAdminLivewire.dashboard.widgets', function ( array $widgets ): array {
    return array_values( array_filter( $widgets, fn ( array $widget ): bool => 'recent-orders' !== $widget['key'] ) );
} );
```

To replace one, add a widget with the same `key`.

## First run

On a store with no products and no orders, the core widgets are replaced by the first-run state. Widgets you add still render. If your widget only makes sense with store data, check for it yourself.
