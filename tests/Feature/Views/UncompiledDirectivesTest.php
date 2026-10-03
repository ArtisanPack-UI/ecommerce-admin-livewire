<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Categories\Index as CategoriesIndex;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\Index as OrdersIndex;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Form as ProductForm;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\Form as PromotionForm;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Settings\Show as SettingsShow;
use Livewire\Livewire;

/*
 * Blade does not compile directives inside the attributes passed to a
 * component, so `<x-artisanpack-button wire:click="go( @js( $x ) )" />`
 * reaches the browser as literal text and the click does nothing. Use
 * `{{ \Illuminate\Support\Js::from( $x ) }}` instead. This guards the
 * screens where that happened.
 */

beforeEach( function (): void {
    grantAbilities( [
        'order.viewAny', 'product.viewAny', 'product.create', 'product.update', 'product.delete',
        'promotion.viewAny', 'promotion.create', 'promotion.update', 'settings.view', 'settings.update',
    ] );
    $this->actingAs( makeUser() );
} );

it( 'renders no uncompiled Blade directives', function ( string $component, Closure $parameters, Closure $prepare ): void {
    $html = $prepare( Livewire::test( $component, $parameters() ) )->html();

    expect( $html )->not->toContain( '@js(' )
        ->and( $html )->not->toMatch( '/="[^"]*@(json|js|lang|php)\b/' );
} )->with( [
    'orders table (sortable headers)' => [ OrdersIndex::class, fn (): array => [], fn ( $component ) => $component ],
    'categories (reorder, media)'     => [ CategoriesIndex::class, fn (): array => [], function ( $component ) {
        ProductCategory::query()->create( [ 'name' => 'One', 'slug' => 'one' ] );
        ProductCategory::query()->create( [ 'name' => 'Two', 'slug' => 'two' ] );

        return $component->call( '$refresh' )->call( 'create', null );
    } ],
    'product form (gallery, media)'   => [ ProductForm::class, fn (): array => [ 'product' => Product::factory()->create()->id ], fn ( $component ) => $component->call( 'addGalleryUrl' )->call( 'addGalleryUrl' ) ],
    'promotion rule builder'          => [ PromotionForm::class, fn (): array => [], fn ( $component ) => $component
        ->set( 'ruleToAdd.conditions', 'min-subtotal' )->call( 'addRule', 'conditions' )
        ->set( 'ruleToAdd.conditions', 'first-order' )->call( 'addRule', 'conditions' ) ],
    'settings (map rows, reset)'      => [ SettingsShow::class, fn (): array => [ 'group' => 'general' ], fn ( $component ) => $component ],
] );
