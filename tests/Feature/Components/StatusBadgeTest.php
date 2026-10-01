<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StatusPresenter;
use Illuminate\Support\Facades\Blade;

it( 'labels every known status in text, with a hidden type label', function ( string $type, string $value, string $label, string $typeLabel ): void {
    $html = Blade::render( '<x-artisanpack-ec-status-badge :type="$type" :value="$value" />', compact( 'type', 'value' ) );

    expect( $html )->toMatch( '/>\s*' . preg_quote( $label, '/' ) . '\s*</' )
        ->toContain( '<span class="sr-only">' . $typeLabel . '</span>' )
        ->toContain( 'data-status-type="' . $type . '"' );
} )->with( [
    'system'      => [ 'system', 'processing', 'Processing', 'Order status:' ],
    'payment'     => [ 'payment', 'partially_refunded', 'Partially refunded', 'Payment status:' ],
    'fulfillment' => [ 'fulfillment', 'partial', 'Partially fulfilled', 'Fulfillment status:' ],
    'review'      => [ 'review', 'spam', 'Spam', 'Review status:' ],
    'shipment'    => [ 'shipment', 'in_transit', 'In transit', 'Shipment status:' ],
] );

it( 'covers every system, payment, fulfillment, and review status the engine uses', function (): void {
    expect( array_keys( StatusPresenter::statuses( 'system' ) ) )->toBe( [ 'pending', 'processing', 'complete', 'cancelled', 'refunded', 'failed' ] )
        ->and( array_keys( StatusPresenter::statuses( 'payment' ) ) )->toBe( [ 'pending', 'paid', 'partially_refunded', 'refunded', 'failed' ] )
        ->and( array_keys( StatusPresenter::statuses( 'fulfillment' ) ) )->toBe( [ 'unfulfilled', 'partial', 'fulfilled' ] )
        ->and( array_keys( StatusPresenter::statuses( 'review' ) ) )->toBe( ArtisanPackUI\Ecommerce\Models\ProductReview::STATUSES );
} );

it( 'falls back to a readable label for unknown values', function (): void {
    expect( StatusPresenter::present( 'system', 'on_hold' ) )->toBe( [ 'label' => 'On Hold', 'color' => 'neutral' ] );
} );

it( 'lets the statusBadge filter describe satellite values', function (): void {
    addFilter( 'ap.ecommerceAdminLivewire.statusBadge', static fn ( array $presented, string $type, string $value ): array => 'on_hold' === $value
        ? [ 'label' => 'Held', 'color' => 'warning' ]
        : $presented );

    expect( StatusPresenter::present( 'system', 'on_hold' ) )->toBe( [ 'label' => 'Held', 'color' => 'warning' ] );

    removeAllFilters( 'ap.ecommerceAdminLivewire.statusBadge' );
} );

it( 'renders a sub-status with its label and hex colour', function (): void {
    $substatus = OrderSubstatus::factory()->create( [ 'label' => 'Awaiting stock', 'color' => '#1d4ed8' ] );

    $html = Blade::render( '<x-artisanpack-ec-status-badge :substatus="$substatus" />', compact( 'substatus' ) );

    expect( $html )->toContain( 'Awaiting stock' )
        ->toContain( 'Sub-status:' )
        ->toContain( 'data-status-type="substatus"' );
} );

it( 'renders nothing without a value', function (): void {
    expect( trim( Blade::render( '<x-artisanpack-ec-status-badge type="payment" :value="null" />' ) ) )->toBe( '' );
} );
