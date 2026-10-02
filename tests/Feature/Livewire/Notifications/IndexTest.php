<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\NotificationTemplate;
use ArtisanPackUI\Ecommerce\Notifications\NotificationCatalog;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notifications\Index;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'notificationTemplate.viewAny', 'notificationTemplate.view', 'notificationTemplate.update' ] );
    $this->actingAs( makeUser() );
} );

it( 'seeds the catalog and lists templates grouped by key', function (): void {
    expect( NotificationTemplate::query()->count() )->toBe( 0 );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSee( 'Order confirmation' )
        ->assertSeeHtml( 'data-template-group="' . NotificationCatalog::ORDER_CONFIRMATION . '"' )
        ->assertSee( 'Email' );

    expect( NotificationTemplate::query()->where( 'key', NotificationCatalog::ORDER_CONFIRMATION )->exists() )->toBeTrue();
} );

it( 'links each row to its editor and shows its state', function (): void {
    $template = NotificationTemplate::factory()->create( [ 'key' => 'test.welcome', 'locale' => 'de', 'is_active' => false ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-template="' . $template->id . '"' )
        ->assertSeeHtml( route( 'artisanpack.ecommerce.admin.notifications.edit', [ 'template' => $template->id ] ) )
        ->assertSee( 'Off' );
} );

it( 'filters by channel and locale', function (): void {
    NotificationTemplate::factory()->create( [ 'key' => 'test.german', 'locale' => 'de' ] );

    Livewire::test( Index::class )
        ->set( 'locale', 'de' )
        ->assertSeeHtml( 'data-template-group="test.german"' )
        ->assertDontSeeHtml( 'data-template-group="' . NotificationCatalog::ORDER_CONFIRMATION . '"' )
        ->call( 'resetFilters' )
        ->assertSeeHtml( 'data-template-group="' . NotificationCatalog::ORDER_CONFIRMATION . '"' )
        ->set( 'channel', 'sms' )
        ->assertSee( 'No matches' );
} );

it( 'is denied without notificationTemplate.viewAny', function (): void {
    Gate::define( 'ecommerce.notificationTemplate.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );
