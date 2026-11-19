<?php

declare( strict_types=1 );

use ArtisanPack\LivewireUiComponents\Support\IconRegistry;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use Illuminate\Support\Carbon;

it( 'shows each state with its own color and icon, behind a hidden prefix', function ( array $attributes, string $state, string $label, string $color, string $icon ): void {
    $html = view( 'ecommerce-admin::livewire.promotions.cells.state', [ 'row' => Promotion::factory()->make( $attributes ) ] )->render();

    expect( $html )->toContain( 'class="apui-badge"' )
        ->toContain( 'data-color="' . $color . '"' )
        ->toContain( 'data-promotion-state="' . $state . '"' )
        ->toContain( '<span class="apui-sr-only">Promotion state:</span>' . $label )
        ->toContain( IconRegistry::svg( $icon ) );
} )->with( [
    'active'    => [ [], 'active', 'Active', 'success', 'check' ],
    'scheduled' => [ [ 'starts_at' => Carbon::now()->addDay() ], 'scheduled', 'Scheduled', 'info', 'clock' ],
    'expired'   => [ [ 'ends_at' => Carbon::now()->subDay() ], 'expired', 'Expired', 'neutral', 'calendar' ],
    'disabled'  => [ [ 'is_active' => false ], 'disabled', 'Switched off', 'warning', 'minus' ],
] );
