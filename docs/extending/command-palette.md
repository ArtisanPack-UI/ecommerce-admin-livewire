---
title: Command Palette Providers
menu_order: 30
---

# Command Palette Providers

Each source of command palette results is a provider implementing `ArtisanPackUI\EcommerceAdminLivewire\Spotlight\SpotlightProvider`. Add or replace providers with the `ap.ecommerceAdminLivewire.spotlight.providers` filter.

## The interface

```php
namespace ArtisanPackUI\EcommerceAdminLivewire\Spotlight;

use Illuminate\Contracts\Auth\Authenticatable;

interface SpotlightProvider
{
    /** The ability the user needs before the provider runs (`order.viewAny`), or null for none. */
    public function ability(): ?string;

    /**
     * @return array<int, array{name: string, description?: string|null, link: string, icon?: string|null}>
     */
    public function search( string $search, Authenticatable $user, int $limit ): array;
}
```

A provider may also define `label(): string`, the heading of its group in the palette ("Subscriptions"). It isn't part of the interface, so existing providers keep working. Without it, the group is labeled with the provider's key in headline case (`loyalty-cards` becomes "Loyalty Cards"). The core providers' labels are fixed.

**Since:** 2.0.0 (`label()`)

- `$search` is trimmed and never empty.
- Return at most `$limit` results (the `spotlight.limit` config, 5 by default) and run at most one query.
- `icon` is a design-system icon name (`shopping-bag`), or a Heroicon name (`o-cube`) that the admin maps to its design-system counterpart. The palette draws icons in the browser, so only icons that `livewire-ui-components` ships to the browser show. Any other name, and any markup, gets no icon.
- Results without `name` or `link` are dropped.
- Check record-level access yourself: the palette only checks `ability()`.

## The filter

```php
applyFilters( 'ap.ecommerceAdminLivewire.spotlight.providers', array $providers, ?Authenticatable $user ): array
```

`$providers` is keyed by name. Values are provider instances or class names (resolved from the container). The core keys, in order, are `orders`, `products`, `customers`, `promotions`, `coupons`, and `actions`. Each provider with results gets its own group, in provider order, with the key as the group's `id`.

## Example

```php
use ArtisanPackUI\EcommerceAdminLivewire\Spotlight\SpotlightProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class SubscriptionsProvider implements SpotlightProvider
{
    public function ability(): ?string
    {
        return 'order.viewAny';
    }

    public function label(): string
    {
        return __( 'Subscriptions' );
    }

    public function search( string $search, Authenticatable $user, int $limit ): array
    {
        return Subscription::query()
            ->where( 'reference', 'like', '%' . $search . '%' )
            ->limit( $limit )
            ->get()
            ->filter( fn ( Subscription $subscription ): bool => $user->can( 'view', $subscription ) )
            ->map( fn ( Subscription $subscription ): array => [
                'name'        => __( 'Subscription :reference', [ 'reference' => $subscription->reference ] ),
                'description' => $subscription->customer_email,
                'link'        => route( 'subscriptions.admin.show', $subscription ),
                'icon'        => 'refresh-cw',
            ] )
            ->values()
            ->all();
    }
}
```

```php
addFilter( 'ap.ecommerceAdminLivewire.spotlight.providers', function ( array $providers ): array {
    $providers['subscriptions'] = SubscriptionsProvider::class;

    return $providers;
} );
```

Escape `%` and `_` in the search term if literal matches matter to you. The core providers do.

To remove a core provider, `unset( $providers['coupons'] )`. To replace one, set the same key.

The core `actions` provider is special: the palette lists its actions before you type, so the search route leaves it out. If you replace it with your own class (or a subclass), it's searched like any other provider instead.

## What the palette handles for you

- Users who cannot open any admin screen get no results.
- Each user is limited to 120 searches a minute.
- The search text is capped in length.
- `spotlight.enabled` set to `false` turns off every provider.
