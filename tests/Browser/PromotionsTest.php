<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Promotion;
use Tests\Browser\Support\Locators;

it( 'builds a promotion with one condition and one action', function (): void {
    $page = visit( route( 'artisanpack.ecommerce.admin.promotions.create' ) )
        ->type( Locators::label( 'Name' ), 'Browser sale' )
        ->click( Locators::role( 'Rules', 'tab' ) )
        ->select( Locators::field( '-add-conditions' ), 'min-subtotal' )
        ->click( 'section[data-rule-list="conditions"] >> ' . Locators::role( 'Add' ) )
        ->type( Locators::field( 'config-ruleRows-conditions-0-config-amount' ), '50' )
        ->select( Locators::field( '-add-actions' ), 'percent-off-cart' )
        ->click( 'section[data-rule-list="actions"] >> ' . Locators::role( 'Add' ) )
        ->type( Locators::field( 'config-ruleRows-actions-0-config-percent' ), '10' );

    $page->click( '[data-save]' )
        ->assertSee( 'Browser sale' )
        ->assertNoJavaScriptErrors();

    $promotion = Promotion::query()->where( 'name', 'Browser sale' )->sole();

    expect( $promotion->conditions()->pluck( 'type' )->all() )->toBe( [ 'min-subtotal' ] )
        ->and( $promotion->actions()->pluck( 'type' )->all() )->toBe( [ 'percent-off-cart' ] );
} );
