<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\TaxRate;
use Tests\Browser\Support\Locators;

/*
 * Focus and announcements around keyboard reorders and inline
 * confirmations (#61). Focus settles just after Livewire's update, so the
 * checks use assertScript, which retries until it holds.
 */

/**
 * JavaScript: the polite announcement's text.
 */
function announcement(): string
{
    return 'document.getElementById( "ecommerce-admin-announcer" ).textContent';
}

it( 'announces a keyboard reorder and moves focus to the arrow that still works', function (): void {
    ProductCategory::query()->delete();

    foreach ( [ 'Aardvark goods', 'Badger goods', 'Cheetah goods' ] as $position => $name ) {
        ProductCategory::query()->create( [ 'name' => $name, 'slug' => str( $name )->slug()->toString(), 'position' => $position ] );
    }

    $total = 3;

    visit( route( 'artisanpack.ecommerce.admin.categories.index' ) )
        ->click( Locators::role( 'Move Aardvark goods down' ) )
        ->assertScript( announcement(), 'Moved Aardvark goods to position 2 of ' . $total . '.' )
        ->assertScript( 'document.activeElement?.getAttribute( "aria-label" )', 'Move Aardvark goods down' )
        // Back to the top: up is now disabled, so focus moves to down.
        ->click( Locators::role( 'Move Aardvark goods up' ) )
        ->assertScript( announcement(), 'Moved Aardvark goods to position 1 of ' . $total . '.' )
        ->assertScript( 'document.activeElement?.getAttribute( "aria-label" )', 'Move Aardvark goods down' );
} );

it( 'returns focus to the trigger when an inline confirmation is cancelled', function (): void {
    $customer = Customer::query()->firstOrFail();

    visit( route( 'artisanpack.ecommerce.admin.customers.show', [ 'customer' => $customer->id ] ) )
        ->click( '[data-delete-customer]' )
        ->assertSee( 'Delete this customer?' )
        ->click( '[data-customer-delete] >> ' . Locators::role( 'Cancel' ) )
        ->assertScript( 'document.activeElement?.hasAttribute( "data-delete-customer" )', true );
} );

it( 'focuses the tax rate row on edit and the edit button after cancel', function (): void {
    $rate = TaxRate::query()->firstOrFail();

    visit( route( 'artisanpack.ecommerce.admin.tax.index' ) )
        ->click( '[data-focus-key="tax-rate-' . $rate->id . '"]' )
        ->assertScript( 'document.activeElement?.tagName', 'SELECT' )
        ->click( '[data-editing-rate="' . $rate->id . '"] >> ' . Locators::role( 'Cancel' ) )
        ->assertScript( 'document.activeElement?.dataset.focusKey', 'tax-rate-' . $rate->id );
} );

it( 'opens the tab with the first error and focuses that field', function (): void {
    visit( route( 'artisanpack.ecommerce.admin.products.create' ) )
        ->click( Locators::role( 'Pricing', 'tab' ) )
        ->click( '[data-save]' )
        ->assertSee( 'Fix the errors in: General' )
        ->assertScript( 'document.activeElement?.getAttribute( "wire:model.live.debounce.400ms" )', 'name' );
} );
