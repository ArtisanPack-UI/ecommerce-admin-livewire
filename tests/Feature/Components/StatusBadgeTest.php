<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StatusPresenter;
use Illuminate\Support\Facades\Blade;

it( 'labels every known status in text, with a hidden type label', function ( string $type, string $value, string $label, string $typeLabel ): void {
    $html = Blade::render( '<x-artisanpack-ec-status-badge :type="$type" :value="$value" />', compact( 'type', 'value' ) );

    expect( $html )->toMatch( '/>\s*' . preg_quote( $label, '/' ) . '\s*</' )
        ->toContain( '<span class="apui-sr-only">' . $typeLabel . '</span>' )
        ->toContain( 'data-status-type="' . $type . '"' );
} )->with( [
    'system'      => [ 'system', 'processing', 'Processing', 'Order status:' ],
    'payment'     => [ 'payment', 'partially_refunded', 'Partially refunded', 'Payment status:' ],
    'voided'      => [ 'payment', 'voided', 'Voided', 'Payment status:' ],
    'fulfillment' => [ 'fulfillment', 'partial', 'Partially fulfilled', 'Fulfillment status:' ],
    'review'      => [ 'review', 'spam', 'Spam', 'Review status:' ],
    'shipment'    => [ 'shipment', 'in_transit', 'In transit', 'Shipment status:' ],
] );

it( 'covers every system, payment, fulfillment, and review status the engine uses', function (): void {
    expect( array_keys( StatusPresenter::statuses( 'system' ) ) )->toBe( [ 'pending', 'processing', 'complete', 'cancelled', 'refunded', 'failed' ] )
        ->and( array_keys( StatusPresenter::statuses( 'payment' ) ) )->toBe( [ 'pending', 'paid', 'partially_refunded', 'refunded', 'voided', 'failed' ] )
        ->and( array_keys( StatusPresenter::statuses( 'fulfillment' ) ) )->toBe( [ 'unfulfilled', 'partial', 'fulfilled' ] )
        ->and( array_keys( StatusPresenter::statuses( 'review' ) ) )->toBe( ArtisanPackUI\Ecommerce\Models\ProductReview::STATUSES );
} );

it( 'labels the payment status the engine sets when it voids an order', function (): void {
    expect( StatusPresenter::statuses( 'payment' ) )->toHaveKey( ArtisanPackUI\Ecommerce\Services\OrderCancellationService::PAYMENT_STATUS_VOIDED );
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

it( 'renders a soft design-system badge with a dot in the status color', function ( string $type, string $value, string $color ): void {
    $html = Blade::render( '<x-artisanpack-ec-status-badge :type="$type" :value="$value" />', compact( 'type', 'value' ) );

    expect( $html )->toContain( 'class="apui-badge"' )
        ->toContain( 'data-variant="soft"' )
        ->toContain( 'data-color="' . $color . '"' )
        ->toContain( 'data-size="md"' )
        ->toContain( 'data-part="dot"' );
} )->with( [
    'needs action' => [ 'system', 'pending', 'warning' ],
    'in progress'  => [ 'payment', 'refunded', 'info' ],
    'done'         => [ 'fulfillment', 'fulfilled', 'success' ],
    'failed'       => [ 'shipment', 'exception', 'error' ],
    'closed'       => [ 'payment', 'voided', 'neutral' ],
    'unknown'      => [ 'system', 'on_hold', 'neutral' ],
] );

it( 'takes the sm and lg sizes and falls back to md', function (): void {
    expect( Blade::render( '<x-artisanpack-ec-status-badge type="payment" value="paid" size="sm" />' ) )->toContain( 'data-size="sm"' )
        ->and( Blade::render( '<x-artisanpack-ec-status-badge type="payment" value="paid" size="lg" />' ) )->toContain( 'data-size="lg"' )
        ->and( Blade::render( '<x-artisanpack-ec-status-badge type="payment" value="paid" size="xl" />' ) )->toContain( 'data-size="md"' );
} );

it( 'puts a sub-status hex on the dot of a neutral badge', function (): void {
    $substatus = OrderSubstatus::factory()->create( [ 'label' => 'Gift wrap', 'color' => '#7c3aed' ] );

    $html = Blade::render( '<x-artisanpack-ec-status-badge :substatus="$substatus" />', compact( 'substatus' ) );

    expect( $html )->toContain( 'data-color="neutral"' )
        ->toContain( '<circle cx="4" cy="4" r="3.5" fill="#7c3aed" />' )
        ->toContain( '<span class="apui-sr-only">Sub-status:</span>Gift wrap' );
} );

it( 'renders a sub-status without a valid hex as a plain neutral badge', function (): void {
    $substatus = OrderSubstatus::factory()->create( [ 'label' => 'On hold', 'color' => 'red; background: url(x)' ] );

    $html = Blade::render( '<x-artisanpack-ec-status-badge :substatus="$substatus" />', compact( 'substatus' ) );

    expect( $html )->toContain( 'data-color="neutral"' )
        ->not->toContain( 'ec-hex-dot' )
        ->not->toContain( 'url(x)' );
} );

it( 'maps a filter color the design system lacks to neutral, and a hex to the dot', function (): void {
    addFilter( 'ap.ecommerceAdminLivewire.statusBadge', static fn ( array $presented, string $type, string $value ): array => match ( $value ) {
        'held'   => [ 'label' => 'Held', 'color' => 'badge-ghost' ],
        'packed' => [ 'label' => 'Packed', 'color' => '#0ea5e9' ],
        default  => $presented,
    } );

    expect( Blade::render( '<x-artisanpack-ec-status-badge type="system" value="held" />' ) )->toContain( 'data-color="neutral"' )
        ->and( Blade::render( '<x-artisanpack-ec-status-badge type="system" value="packed" />' ) )->toContain( 'fill="#0ea5e9"' );

    removeAllFilters( 'ap.ecommerceAdminLivewire.statusBadge' );
} );
