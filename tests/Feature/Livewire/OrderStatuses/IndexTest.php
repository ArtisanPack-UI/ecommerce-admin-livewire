<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\OrderStatuses\Index;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ColorContrast;
use ArtisanPackUI\EcommerceAdminLivewire\Support\IconChoices;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'orderSubstatus.viewAny', 'orderSubstatus.create', 'orderSubstatus.update', 'orderSubstatus.delete' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    ColorContrast::fake( null );
    IconChoices::fake( null );
} );

function processingSubstatus( array $attributes = [] ): OrderSubstatus
{
    return OrderSubstatus::query()->create( $attributes + [
        'system_status' => 'processing',
        'key'           => 'printing',
        'label'         => 'Printing',
        'color'         => '#1E3A8A',
        'position'      => 5,
    ] );
}

it( 'renders the sub-statuses grouped under the six system statuses', function (): void {
    $component = Livewire::test( Index::class )->assertOk();

    foreach ( [ 'pending', 'processing', 'complete', 'cancelled', 'refunded', 'failed' ] as $status ) {
        $component->assertSeeHtml( 'data-system-status="' . $status . '"' );
    }

    $component->assertSeeInOrder( [ 'Awaiting Payment', 'In Progress', 'Completed' ] )
        ->assertSeeHtml( 'data-substatus="in-progress"' );
} );

it( 'shows how many orders use each sub-status', function (): void {
    $printing = processingSubstatus();
    Order::factory()->count( 3 )->create( [ 'system_status' => 'processing', 'substatus_id' => $printing->id ] );

    Livewire::test( Index::class )->assertSeeHtml( 'data-order-count="3"' )->assertSee( '3 orders' );
} );

it( 'is denied without orderSubstatus.viewAny', function (): void {
    Gate::define( 'ecommerce.orderSubstatus.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'creates "Printing" under processing', function (): void {
    Livewire::test( Index::class )
        ->call( 'create', 'processing' )
        ->assertSet( 'editing', true )
        ->assertSet( 'form.system_status', 'processing' )
        ->set( 'form.label', 'Printing' )
        ->set( 'form.color', '#1e3a8a' )
        ->set( 'form.icon', 'o-printer' )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'editing', false )
        ->assertSee( 'Printing' );

    $substatus = OrderSubstatus::query()->where( 'system_status', 'processing' )->where( 'label', 'Printing' )->sole();

    expect( $substatus )
        ->key->toBe( 'printing' )
        ->color->toBe( '#1E3A8A' )
        ->icon->toBe( 'o-printer' );
} );

it( 'ignores an unknown system status', function (): void {
    Livewire::test( Index::class )->call( 'create', 'shipped' )->assertSet( 'editing', false );
} );

it( 'validates the form', function (): void {
    Livewire::test( Index::class )
        ->call( 'create', 'processing' )
        ->set( 'form.label', '' )
        ->set( 'form.key', 'Not A Key' )
        ->set( 'form.color', 'blue' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.label' => 'required', 'form.key' => 'regex', 'form.color' => 'regex' ] );

    expect( OrderSubstatus::query()->where( 'system_status', 'processing' )->count() )->toBe( 1 );
} );

it( 'maps the engine refusal of a duplicate key onto the key field', function (): void {
    Livewire::test( Index::class )
        ->call( 'create', 'processing' )
        ->set( 'form.label', 'Again' )
        ->set( 'form.key', 'in-progress' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.key' ] )
        ->assertSet( 'editing', true );
} );

it( 'recolours a sub-status and keeps its key', function (): void {
    $substatus = processingSubstatus( [ 'key' => 'awaiting-label', 'label' => 'Awaiting label' ] );

    Livewire::test( Index::class )
        ->call( 'edit', $substatus->id )
        ->assertSet( 'form.key', 'awaiting-label' )
        ->set( 'form.key', 'changed' )
        ->set( 'form.color', '#FDE68A' )
        ->set( 'form.is_terminal', true )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $substatus->refresh() )
        ->key->toBe( 'awaiting-label' )
        ->color->toBe( '#FDE68A' )
        ->is_terminal->toBeTrue();
} );

it( 'reorders within a system status', function (): void {
    $printing = processingSubstatus();
    $default  = OrderSubstatus::query()->where( 'key', 'in-progress' )->sole();

    Livewire::test( Index::class )
        ->call( 'move', $printing->id, -1 )
        ->assertSeeInOrder( [ 'Printing', 'In Progress' ] );

    expect( $printing->refresh()->position )->toBeLessThan( $default->refresh()->position );

    Livewire::test( Index::class )->call( 'move', $printing->id, -1 );

    expect( $printing->refresh()->position )->toBeLessThan( $default->refresh()->position );
} );

it( 'deletes an unused sub-status after confirmation', function (): void {
    $printing = processingSubstatus();

    $component = Livewire::test( Index::class )
        ->call( 'confirmDelete', $printing->id )
        ->assertSeeHtml( 'data-delete-substatus="printing"' )
        ->assertSee( 'Nothing uses this sub-status.' );

    $token = $component->viewData( 'deleteToken' );

    $component->call( 'delete', $token )->assertSet( 'confirmingDelete', false );

    expect( OrderSubstatus::query()->whereKey( $printing->id )->exists() )->toBeFalse();
} );

it( 'blocks deleting a sub-status that orders use, with the count', function (): void {
    $printing = processingSubstatus();
    Order::factory()->count( 14 )->create( [ 'system_status' => 'processing', 'substatus_id' => $printing->id ] );

    $component = Livewire::test( Index::class )
        ->call( 'confirmDelete', $printing->id )
        ->assertSeeHtml( 'data-delete-in-use' )
        ->assertSee( '14 orders' )
        ->assertViewHas( 'deleteBlocked', true );

    $component->call( 'delete', $component->viewData( 'deleteToken' ) );

    expect( sentToasts( $component ) )->toContain( '14 orders' );
    expect( OrderSubstatus::query()->whereKey( $printing->id )->exists() )->toBeTrue();
} );

it( 'counts kanban columns that use the sub-status', function (): void {
    $printing = processingSubstatus();
    KanbanColumn::factory()->create( [ 'substatus_id' => $printing->id ] );

    Livewire::test( Index::class )
        ->call( 'confirmDelete', $printing->id )
        ->assertSee( '1 kanban column' )
        ->assertViewHas( 'deleteBlocked', true );
} );

it( 'blocks deleting the last sub-status of a system status', function (): void {
    $only = OrderSubstatus::query()->where( 'system_status', 'pending' )->sole();

    $component = Livewire::test( Index::class )
        ->call( 'confirmDelete', $only->id )
        ->assertSeeHtml( 'data-delete-last' )
        ->assertViewHas( 'deleteBlocked', true );

    $component->call( 'delete', $component->viewData( 'deleteToken' ) );

    expect( OrderSubstatus::query()->whereKey( $only->id )->exists() )->toBeTrue();
} );

it( 'warns about a low-contrast colour when the accessibility package is installed', function (): void {
    ColorContrast::fake( true );

    Livewire::test( Index::class )
        ->call( 'create', 'processing' )
        ->set( 'form.color', '#3B82F6' )
        ->assertSeeHtml( 'data-contrast-warning' )
        ->set( 'form.color', '#1E3A8A' )
        ->assertDontSeeHtml( 'data-contrast-warning' );
} );

it( 'skips the contrast warning without the accessibility package', function (): void {
    ColorContrast::fake( false );

    Livewire::test( Index::class )
        ->call( 'create', 'processing' )
        ->set( 'form.color', '#3B82F6' )
        ->assertDontSeeHtml( 'data-contrast-warning' );
} );

it( 'offers an icon picker when the icons package is installed, else a text input', function (): void {
    IconChoices::fake( true );

    Livewire::test( Index::class )
        ->call( 'create', 'processing' )
        ->assertSeeHtml( 'data-icon-picker' )
        ->assertViewHas( 'iconOptions', fn ( array $options ): bool => in_array( 'o-printer', array_column( $options, 'id' ), true ) );

    IconChoices::fake( false );

    Livewire::test( Index::class )
        ->call( 'create', 'processing' )
        ->assertSeeHtml( 'data-icon-text' )
        ->assertDontSeeHtml( 'data-icon-picker' );
} );

it( 'hides controls the user cannot use', function (): void {
    Gate::define( 'ecommerce.orderSubstatus.create', static fn (): bool => false );
    Gate::define( 'ecommerce.orderSubstatus.update', static fn (): bool => false );
    Gate::define( 'ecommerce.orderSubstatus.delete', static fn (): bool => false );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertDontSee( 'Add sub-status' )
        ->assertDontSeeHtml( 'wire:click="edit(' )
        ->assertDontSeeHtml( 'wire:click="confirmDelete(' )
        ->call( 'create', 'processing' )
        ->assertForbidden();
} );

it( 'refuses writes without their abilities', function ( string $ability, string $action, array $args ): void {
    Gate::define( 'ecommerce.' . $ability, static fn (): bool => false );

    Livewire::test( Index::class )->call( $action, ...$args )->assertForbidden();
} )->with( [
    'edit'          => [ 'orderSubstatus.update', 'edit', [ 1 ] ],
    'move'          => [ 'orderSubstatus.update', 'move', [ 1, 1 ] ],
    'confirmDelete' => [ 'orderSubstatus.delete', 'confirmDelete', [ 1 ] ],
    'delete'        => [ 'orderSubstatus.delete', 'delete', [ 'token' ] ],
] );

it( 'saves an icon from a configured icon set and renders it', function (): void {
    $directory = sys_get_temp_dir() . '/ec-admin-icons-' . uniqid();
    mkdir( $directory );
    file_put_contents( $directory . '/home.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><path d="M0 0h1v1H0z"/></svg>' );

    app( BladeUI\Icons\Factory::class )->add( 'fa', [ 'path' => $directory, 'prefix' => 'fa' ] );
    config( [ 'artisanpack.icons.sets' => [ 'fa' => [ 'path' => $directory, 'prefix' => 'fa' ] ] ] );
    IconChoices::fake( true );

    expect( array_column( IconChoices::options(), 'id' ) )->toContain( 'fa.home' );

    Livewire::test( Index::class )
        ->call( 'create', 'processing' )
        ->set( 'form.label', 'Home delivery' )
        ->set( 'form.icon', 'fa.home' )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( OrderSubstatus::query()->where( 'label', 'Home delivery' )->value( 'icon' ) )->toBe( 'fa.home' );

    Livewire::test( Index::class )->assertOk()->assertSee( 'Home delivery' );
} );

it( 'rejects an icon name that does not resolve', function (): void {
    IconChoices::fake( false );

    Livewire::test( Index::class )
        ->call( 'create', 'processing' )
        ->set( 'form.label', 'Typo' )
        ->set( 'form.icon', 'o-printr' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.icon' ] );

    IconChoices::fake( true );

    Livewire::test( Index::class )
        ->call( 'create', 'processing' )
        ->set( 'form.label', 'Typo' )
        ->set( 'form.icon', 'fa.missing' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.icon' ] );

    expect( OrderSubstatus::query()->where( 'label', 'Typo' )->exists() )->toBeFalse();
} );

it( 'still renders when a stored icon no longer resolves', function (): void {
    processingSubstatus( [ 'icon' => 'o-does-not-exist' ] );

    Livewire::test( Index::class )->assertOk()->assertSee( 'Printing' );
} );

it( 'does not recreate a sub-status deleted while it was being edited', function (): void {
    $substatus = processingSubstatus();

    $component = Livewire::test( Index::class )->call( 'edit', $substatus->id );

    $substatus->delete();

    $component->set( 'form.label', 'Printing again' )
        ->call( 'save' )
        ->assertSet( 'editing', false );

    expect( OrderSubstatus::query()->where( 'key', 'printing' )->exists() )->toBeFalse()
        ->and( sentToasts( $component ) )->toContain( 'This sub-status was deleted.' );
} );

it( 'renders a non-hex colour from the API as transparent', function (): void {
    $substatus = OrderSubstatus::factory()->forSystemStatus( 'processing' )->create( [ 'label' => 'Odd', 'color' => 'red;x:y' ] );

    Livewire::test( Index::class )
        ->assertSee( 'Odd' )
        ->assertDontSeeHtml( 'red;x:y' )
        ->assertSeeHtml( 'background-color: transparent' );

    expect( $substatus->exists )->toBeTrue()
        ->and( ColorContrast::safeHex( '#a1B2c3' ) )->toBe( '#a1B2c3' )
        ->and( ColorContrast::safeHex( '#abc' ) )->toBe( 'transparent' );
} );
