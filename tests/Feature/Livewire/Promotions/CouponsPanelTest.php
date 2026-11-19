<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\ActivityLogEntry;
use ArtisanPackUI\Ecommerce\Models\Coupon;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\CouponsPanel;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'promotion.viewAny', 'promotion.view', 'coupon.create', 'coupon.update', 'coupon.delete' ] );
    $this->actingAs( makeUser() );

    $this->promotion = Promotion::factory()->coupon()->create( [ 'key' => 'welcome', 'times_used' => 3 ] );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerceAdminLivewire.coupons.usage' );
} );

/**
 * The one-time token the code delete button carries.
 */
function couponDeleteToken( $component ): string
{
    expect( preg_match( "/deleteCode\\( '([^']+)' \\)/", $component->html(), $matches ) )->toBe( 1 );

    return $matches[1];
}

it( 'lists the promotion\'s codes and explains how uses are counted', function (): void {
    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'WELCOME10' ] );
    Coupon::factory()->create( [ 'code' => 'SOMEONEELSE' ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->assertOk()
        ->assertSee( [ 'WELCOME10', '1 code in all', 'not per code' ] )
        ->assertDontSee( 'SOMEONEELSE' );
} );

it( 'is denied without promotion.view', function (): void {
    Gate::define( 'ecommerce.promotion.view', static fn (): bool => false );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )->assertForbidden();
} );

it( 'adds a code, normalized', function (): void {
    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'newCode', '  welcome10 ' )
        ->call( 'addCode' )
        ->assertHasNoErrors()
        ->assertSet( 'newCode', '' )
        ->assertSee( 'WELCOME10' );

    expect( Coupon::query()->where( 'promotion_id', $this->promotion->id )->pluck( 'code' )->all() )->toBe( [ 'WELCOME10' ] );
} );

it( 'keeps codes unique across the store', function (): void {
    Coupon::factory()->create( [ 'code' => 'WELCOME10' ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'newCode', 'Welcome10' )
        ->call( 'addCode' )
        ->assertHasErrors( [ 'newCode' => 'unique' ] );
} );

it( 'validates the code', function ( string $code, string $rule ): void {
    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'newCode', $code )
        ->call( 'addCode' )
        ->assertHasErrors( [ 'newCode' => $rule ] );
} )->with( [
    'blank'       => [ '   ', 'required' ],
    'spaces'      => [ 'HELLO THERE', 'regex' ],
    'punctuation' => [ 'SAVE!', 'regex' ],
    'too long'    => [ str_repeat( 'A', 81 ), 'max' ],
] );

it( 'renames a code', function (): void {
    $coupon = Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'WELCOM10' ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->call( 'startRename', $coupon->id )
        ->assertSet( 'editingCode', 'WELCOM10' )
        ->set( 'editingCode', 'welcome10' )
        ->call( 'saveRename' )
        ->assertHasNoErrors()
        ->assertSet( 'editingId', null );

    expect( $coupon->fresh()->code )->toBe( 'WELCOME10' );
} );

it( 'ignores codes from other promotions', function (): void {
    $other = Coupon::factory()->create( [ 'code' => 'NOTYOURS' ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->call( 'startRename', $other->id )
        ->assertSet( 'editingId', null )
        ->call( 'confirmDelete', $other->id )
        ->assertSet( 'deletingId', null );
} );

it( 'deletes a leaked code after confirming', function (): void {
    $coupon = Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'LEAKED' ] );

    $component = Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->call( 'confirmDelete', $coupon->id )
        ->assertSee( 'It stops working at checkout right away.' );

    $component->call( 'deleteCode', couponDeleteToken( $component ) );

    expect( Coupon::query()->whereKey( $coupon->id )->exists() )->toBeFalse();
} );

it( 'generates unique codes from the unambiguous alphabet', function (): void {
    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'generateCount', 500 )
        ->set( 'generatePrefix', 'fall-' )
        ->set( 'generateLength', 6 )
        ->call( 'generateCodes' )
        ->assertHasNoErrors();

    $codes = Coupon::query()->where( 'promotion_id', $this->promotion->id )->pluck( 'code' );

    expect( $codes )->toHaveCount( 500 )
        ->and( $codes->unique() )->toHaveCount( 500 )
        ->and( $codes->every( static fn ( string $code ): bool => 1 === preg_match( '/^FALL-[' . CouponsPanel::ALPHABET . ']{6}$/', $code ) ) )->toBeTrue()
        ->and( ActivityLogEntry::query()->where( 'event_type', 'coupons.generated' )->value( 'payload' ) )->toMatchArray( [ 'count' => 500, 'prefix' => 'FALL-' ] );
} );

it( 'validates the generate settings', function (): void {
    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'generateCount', CouponsPanel::MAX_GENERATE + 1 )
        ->set( 'generatePrefix', 'no spaces' )
        ->set( 'generateLength', 3 )
        ->call( 'generateCodes' )
        ->assertHasErrors( [ 'generateCount' => 'max', 'generatePrefix' => 'regex', 'generateLength' => 'min' ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'generatePrefix', str_repeat( 'A', 20 ) )
        ->set( 'generateLength', 32 )
        ->set( 'generateCount', 1 )
        ->call( 'generateCodes' );

    expect( Coupon::query()->where( 'promotion_id', $this->promotion->id )->count() )->toBe( 1 );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'generatePrefix', str_repeat( 'A', 20 ) )
        ->set( 'generateLength', 32 )
        ->set( 'generateCount', 1 )
        ->call( 'generateCodes' );

    expect( Coupon::query()->where( 'promotion_id', $this->promotion->id )->count() )->toBe( 2 );
} );

it( 'exports the codes to CSV', function (): void {
    grantAbilities( [ 'promotion.update' ] );
    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'B-CODE' ] );
    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'A-CODE' ] );

    $component = Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )->call( 'exportCodes' );
    $download  = $component->effects['download'] ?? null;

    expect( $download['name'] )->toStartWith( 'coupons-welcome-' );

    $content = base64_decode( (string) $download['content'] );

    expect( strpos( $content, 'A-CODE' ) )->toBeLessThan( strpos( $content, 'B-CODE' ) )
        ->and( $content )->toContain( 'Code' );
} );

it( 'does not let a user who may only view the promotion export its codes', function (): void {
    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'SECRET' ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->call( 'exportCodes' )
        ->assertForbidden();
} );

it( 'refuses to add codes to an automatic promotion', function (): void {
    $automatic = Promotion::factory()->create( [ 'source_type' => Promotion::SOURCE_AUTOMATIC ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $automatic ] )
        ->set( 'newCode', 'SPRING' )
        ->call( 'addCode' )
        ->assertHasErrors( 'newCode' )
        ->set( 'generateCount', 5 )
        ->call( 'generateCodes' );

    expect( Coupon::query()->where( 'promotion_id', $automatic->id )->count() )->toBe( 0 );
} );

it( 'searches codes', function (): void {
    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'SPRING_10' ] );
    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'SPRINGX10' ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'search', 'g_1' )
        ->assertSee( 'SPRING_10' )
        ->assertDontSee( 'SPRINGX10' );
} );

it( 'shows per-code uses when something records them', function (): void {
    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'TRACKED' ] );

    addFilter( 'ap.ecommerceAdminLivewire.coupons.usage', static fn ( array $counts ): array => [ 'TRACKED' => 7 ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->assertDontSeeHtml( 'data-coupon-usage-note' )
        ->assertSeeInOrder( [ 'Uses', 'TRACKED', '7' ] );
} );

it( 'refuses writes without the coupon abilities', function (): void {
    Gate::define( 'ecommerce.coupon.create', static fn (): bool => false );
    Gate::define( 'ecommerce.coupon.delete', static fn (): bool => false );

    $coupon = Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->assertDontSeeHtml( 'data-add-code' )
        ->set( 'newCode', 'NEW' )
        ->call( 'addCode' )
        ->assertForbidden();

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->call( 'confirmDelete', $coupon->id )
        ->assertForbidden();
} );

it( 'reports a code taken by a concurrent request instead of failing', function (): void {
    Coupon::creating( static function ( Coupon $coupon ): void {
        if ( 'RACED' === $coupon->code && ! Coupon::query()->where( 'code', 'RACED' )->exists() ) {
            Illuminate\Support\Facades\DB::table( ( new Coupon() )->getTable() )->insert( [ 'promotion_id' => $coupon->promotion_id, 'code' => 'RACED', 'created_at' => now(), 'updated_at' => now() ] );
        }
    } );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->set( 'newCode', 'raced' )
        ->call( 'addCode' )
        ->assertOk()
        ->assertHasErrors( 'newCode' );

    expect( Coupon::query()->where( 'code', 'RACED' )->count() )->toBe( 1 );
} );

it( 'builds the coupons panel from design-system parts with no DaisyUI classes', function (): void {
    grantAbilities( [ 'promotion.update' ] );
    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'FALL15' ] );

    $html = Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )->html();

    expect( $html )
        ->toContain( 'data-surface="sunken"' )
        ->toContain( 'ec-coupon-forms' )
        ->toContain( 'class="apui-table ec-table"' )
        ->toContain( '<bdi dir="ltr" class="ec-mono ec-strong">FALL15</bdi>' )
        ->toContain( 'aria-label="Rename FALL15"' )
        ->toContain( 'aria-label="Delete FALL15"' )
        ->toContain( 'aria-label="Find a code"' )
        ->toContain( 'data-export-codes' )
        ->not->toContain( 'class="table' )
        ->not->toContain( 'rounded-box' )
        ->not->toContain( 'opacity-75' )
        ->not->toContain( 'input-sm' );

    // The code inputs read left to right in mono, and the new code is named by its heading.
    expect( Illuminate\Support\Str::betweenFirst( $html, 'data-add-code', '</form>' ) )
        ->toContain( 'aria-labelledby="coupon-new-code-heading"' )
        ->toContain( 'ec-mono-input' )
        ->toContain( 'dir="ltr"' );

    // The engine counts uses per promotion, so the note (not a Uses column) points to the Usage tab.
    expect( $html )->toContain( 'data-coupon-usage-note' )->not->toContain( '>Uses<' );
} );

it( 'renames a code in its row', function (): void {
    $coupon = Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'FALL15' ] );

    $html = Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->call( 'startRename', $coupon->id )
        ->html();

    $row = Illuminate\Support\Str::betweenFirst( $html, 'data-rename-code="' . $coupon->id . '"', '</tr>' );

    expect( $row )
        ->toContain( 'aria-label="Code"' )
        ->toContain( 'data-focus-return="coupon-rename-' . $coupon->id . '"' )
        ->not->toContain( 'aria-label="Rename FALL15"' )
        ->and( $html )->toContain( 'x-on:keydown.escape.prevent="$wire.cancelRename()"' );
} );

it( 'confirms a delete in place of the row', function (): void {
    $coupon = Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'VIP-FALL' ] );

    $html = Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )
        ->call( 'confirmDelete', $coupon->id )
        ->html();

    expect( $html )
        ->toContain( 'colspan="3"' )
        ->toContain( 'role="alertdialog"' )
        ->toContain( 'Delete VIP-FALL? It stops working at checkout right away.' )
        ->not->toContain( 'aria-label="Delete VIP-FALL"' );
} );

it( 'leads with a warning when the promotion is not a coupon promotion', function (): void {
    $automatic = Promotion::factory()->create( [ 'source_type' => Promotion::SOURCE_AUTOMATIC ] );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $automatic ] )
        ->assertSeeHtml( 'data-wrong-source' )
        ->assertSeeHtml( 'class="apui-alert' )
        ->assertSee( 'Codes only work on coupon promotions.' );

    Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] )->assertDontSeeHtml( 'data-wrong-source' );
} );

it( 'tells no codes apart from no matches', function (): void {
    grantAbilities( [ 'promotion.update' ] );

    $component = Livewire::test( CouponsPanel::class, [ 'promotion' => $this->promotion ] );

    expect( $component->html() )
        ->toContain( 'No codes yet' )
        ->toContain( 'ec-empty-sunken' )
        ->not->toContain( 'data-coupon-usage-note' );

    // With nothing to export, the button stays but is marked unavailable and calls nothing.
    expect( Illuminate\Support\Str::betweenFirst( $component->html(), 'aria-disabled="true"', 'Export codes (CSV)' ) )->toContain( 'data-export-codes' )
        ->and( $component->html() )->not->toContain( 'wire:click="exportCodes"' );

    Coupon::factory()->create( [ 'promotion_id' => $this->promotion->id, 'code' => 'FALL15' ] );

    $component->set( 'search', 'nothing' );

    expect( $component->html() )
        ->toContain( 'No matching codes' )
        ->toContain( 'Try another search.' )
        ->toContain( '1 code in all' )
        ->not->toContain( 'data-coupon-usage-note' );
} );
