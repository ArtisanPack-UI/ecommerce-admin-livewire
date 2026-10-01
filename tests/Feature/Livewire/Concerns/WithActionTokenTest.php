<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\IdempotencyRecord;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderNote;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ActionTokens;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\TokenScreen;

beforeEach( function (): void {
    $this->actingAs( makeUser() );
    $this->order = Order::factory()->create();
} );

/**
 * The token the rendered refund button carries.
 */
function refundToken( $component ): string
{
    preg_match( "/refund\\( '([^']+)' \\)/", $component->html(), $matches );

    return $matches[1];
}

it( 'mints a token into the button and disables it while loading', function (): void {
    $component = Livewire::test( TokenScreen::class, [ 'orderId' => $this->order->id ] );

    expect( refundToken( $component ) )->toMatch( '/^[A-Za-z0-9]{40}\.\d+\.[0-9a-f]{64}$/' )
        ->and( $component->html() )->toContain( 'wire:loading.attr="disabled"' );
} );

it( 'runs the write once when the same token is submitted twice', function (): void {
    $component = Livewire::test( TokenScreen::class, [ 'orderId' => $this->order->id ] );
    $token     = refundToken( $component );

    $component->call( 'refund', $token )->assertOk();
    $component->call( 'refund', $token )->assertOk();

    expect( OrderNote::query()->count() )->toBe( 1 )
        ->and( IdempotencyRecord::query()->count() )->toBe( 1 )
        ->and( sentToasts( $component ) )->toContain( 'That action was already submitted.' );
} );

it( 'mints a fresh token on every render, so a deliberate second action works', function (): void {
    $component = Livewire::test( TokenScreen::class, [ 'orderId' => $this->order->id ] );

    $component->call( 'refund', refundToken( $component ) );
    $component->call( 'refund', refundToken( $component ) );

    expect( OrderNote::query()->count() )->toBe( 2 );
} );

it( 'releases the token when the write fails, so a retry can succeed', function (): void {
    $component = Livewire::test( TokenScreen::class, [ 'orderId' => $this->order->id ] );
    $token     = refundToken( $component );

    $component->set( 'failNext', true );

    expect( fn () => $component->call( 'refund', $token ) )->toThrow( RuntimeException::class );
    expect( OrderNote::query()->count() )->toBe( 0 )
        ->and( IdempotencyRecord::query()->count() )->toBe( 0 );

    $component->set( 'failNext', false )->call( 'refund', $token );

    expect( OrderNote::query()->count() )->toBe( 1 );
} );

it( 'refuses a token minted for another order', function (): void {
    $other     = Order::factory()->create();
    $component = Livewire::test( TokenScreen::class, [ 'orderId' => $this->order->id ] );
    $token     = refundToken( $component );

    $component->set( 'orderId', $other->id )->call( 'refund', $token );

    expect( OrderNote::query()->count() )->toBe( 0 )
        ->and( sentToasts( $component ) )->toContain( 'This action has expired.' );
} );

it( 'refuses a token minted for another user', function (): void {
    $component = Livewire::test( TokenScreen::class, [ 'orderId' => $this->order->id ] );
    $token     = refundToken( $component );

    $this->actingAs( makeUser() );
    $component->call( 'refund', $token );

    expect( OrderNote::query()->count() )->toBe( 0 );
} );

it( 'refuses forged, malformed, and expired tokens', function ( Closure $tamper ): void {
    $component = Livewire::test( TokenScreen::class, [ 'orderId' => $this->order->id ] );

    $component->call( 'refund', $tamper( refundToken( $component ) ) );

    expect( OrderNote::query()->count() )->toBe( 0 );
} )->with( [
    'forged signature' => [ static fn ( string $token ): string => substr( $token, 0, -1 ) . ( str_ends_with( $token, 'a' ) ? 'b' : 'a' ) ],
    'malformed'        => [ static fn (): string => 'not-a-token' ],
    'expired'          => [ static function ( string $token ): string {
        Carbon::setTestNow( Carbon::now()->addSeconds( ActionTokens::TTL_SECONDS + 1 ) );

        return $token;
    } ],
] );

afterEach( function (): void {
    Carbon::setTestNow();
} );
