---
title: Command Palette Providers
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

- `$search` is trimmed and never empty.
- Return at most `$limit` results (the `spotlight.limit` config, 5 by default) and run at most one query.
- `icon` is a Heroicon name (`o-cube`). The palette renders the icon itself; markup in `icon` is ignored.
- Results without `name` or `link` are dropped.
- Check record-level access yourself: the palette only checks `ability()`.

## The filter

```php
applyFilters( 'ap.ecommerceAdminLivewire.spotlight.providers', array $providers, ?Authenticatable $user ): array
```

`$providers` is keyed by name. Values are provider instances or class names (resolved from the container). The core keys, in order, are `orders`, `products`, `customers`, `promotions`, `coupons`, and `actions`. Results appear in provider order.

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
                'icon'        => 'o-arrow-path',
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

## What the palette handles for you

- Users who cannot open any admin screen get no results.
- Each user is limited to 120 searches a minute.
- The search text is capped in length.
- `spotlight.enabled` set to `false` turns off every provider.
