---
title: Extending Overview
---

# Extending

Other packages (satellites) and host applications extend the admin through registries and filters. Nothing here requires publishing views.

## In this section

- [Navigation](Extending-Navigation) - Add sections, entries, and badges to the admin navigation
- [Dashboard Widgets](Extending-Dashboard-Widgets) - Add, replace, or remove dashboard widgets
- [Command Palette Providers](Extending-Command-Palette) - Add result sources to Cmd+K
- [Order Panels](Extending-Order-Panels) - Add a panel to the order detail page
- [Product Type Panels](Extending-Product-Type-Panels) - Add the type tab for your own product type
- [Customer Tabs](Extending-Customer-Tabs) - Add a tab to the customer detail page
- [Settings Tabs](Extending-Settings-Tabs) - Add a settings group with its own screen
- [Config Forms](Extending-Config-Forms) - Describe the settings form for a promotion rule, shipping method, or kanban entry
- [Pickers](Extending-Pickers) - Add a searchable picker for your own records
- [Resource Tables](Extending-Resource-Tables) - Add columns, filters, and bulk actions to index screens
- [Layout](Extending-Layout) - Change which Vite entries the layout loads
- [Blade Components](Extending-Blade-Components) - The `x-artisanpack-ec-*` components to reuse in your panels
- [Hooks Reference](Extending-Hooks-Reference) - Every filter and browser event the admin uses

## Two kinds of extension point

**Registries** are singletons under `ArtisanPackUI\EcommerceAdminLivewire\Registries\`. Resolve one from the container and register in your service provider's `boot()`:

```php
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;

public function boot(): void
{
    app( OrderPanelRegistry::class )->register( 'loyalty', 'loyalty-order-panel', 'side', 30 );
}
```

| Registry | Adds |
| --- | --- |
| `OrderPanelRegistry` | Order detail panels |
| `ProductTypePanelRegistry` | Product form type tabs |
| `CustomerTabRegistry` | Customer detail tabs |
| `SettingsTabRegistry` | Settings tabs with their own component |
| `ConfigFormRegistry` | Field schemas for engine registry `config` blobs |
| `PickerSourceRegistry` | Picker search sources |

**Filters** use `artisanpack-ui/hooks`. Register them with `addFilter()`, usually in `boot()`:

```php
addFilter( 'ap.ecommerceAdminLivewire.dashboard.widgets', function ( array $widgets ): array {
    $widgets[] = [ /* … */ ];

    return $widgets;
} );
```

## Rules every extension follows

- **Register your own Livewire components.** Panels and tabs are Livewire components you register with `Livewire::component()` in your package.
- **Authorize inside your component.** The admin only guarantees the user can view the page's record. Check the abilities your panel needs in `mount()` and in every action. Use `ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization::allows( $user, 'order.update', $order )` or the engine's policies.
- **Abilities are `{resource}.{action}`.** An `ecommerce.` prefix is accepted where an entry declares a `permission`. A malformed permission drops the entry and logs a warning; it never breaks the admin.
- **Translate labels per request.** Registries that take a label accept a closure (`fn (): string => __( 'Points' )`) so it is translated in the request's locale.
- **A later registration with the same key replaces an earlier one.** Use that to replace a built-in panel or tab. `unregister()` (or `forget()` for product types) removes one.
