<?php

declare( strict_types=1 );

namespace Tests\Fixtures\Livewire;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\ProductTypePanel;

/**
 * A satellite's product-type panel, registered through ProductTypePanelRegistry.
 *
 * @since 1.0.0
 */
class SubscriptionProductPanel extends ProductTypePanel
{
    /**
     * @since 1.0.0
     *
     * @return string
     */
    public static function label(): string
    {
        return 'Subscription';
    }

    /**
     * @since 1.0.0
     *
     * @param  Product|null  $product  Product.
     *
     * @return array<string, mixed>
     */
    public static function initialState( ?Product $product ): array
    {
        return [ 'interval' => (string) ( $product?->meta['interval'] ?? 'month' ) ];
    }

    /**
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $state    State.
     * @param  Product|null          $product  Product.
     *
     * @return array<string, mixed>
     */
    public static function rules( array $state, ?Product $product ): array
    {
        return [ 'interval' => [ 'required', 'in:week,month,year' ] ];
    }

    /**
     * @since 1.0.0
     *
     * @param  Product               $product  Product.
     * @param  array<string, mixed>  $state    State.
     *
     * @return void
     */
    public static function save( Product $product, array $state ): void
    {
        $product->forceFill( [ 'meta' => [ 'interval' => $state['interval'] ] + (array) $product->meta ] )->save();
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public function render(): string
    {
        return '<div data-subscription-panel>Bills every <input wire:model="state.interval" /></div>';
    }
}
