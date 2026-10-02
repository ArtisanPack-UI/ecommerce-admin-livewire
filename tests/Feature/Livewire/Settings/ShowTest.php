<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Settings\SettingsRepository;
use ArtisanPackUI\EcommerceAdminLivewire\EcommerceAdminLivewireServiceProvider;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Settings\Show;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\SettingsTabRegistry;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Fixtures\Livewire\LoyaltySettingsTab;

beforeEach( function (): void {
    grantAbilities( [ 'settings.view', 'settings.update' ] );
    $this->actingAs( makeUser() );
} );

function settingsRepository(): SettingsRepository
{
    return app( SettingsRepository::class );
}

it( 'renders a group as a form with a tab for every group', function (): void {
    config()->set( 'artisanpack.ecommerce.checkout.reservation_ttl_minutes', 15 );
    settingsRepository()->refresh();

    Livewire::test( Show::class, [ 'group' => 'checkout' ] )
        ->assertOk()
        ->assertSee( 'Stock reservation (minutes)' )
        ->assertSet( 'form.checkout__reservation_ttl_minutes', '15' )
        ->assertSeeInOrder( [ 'General', 'Checkout', 'Tax', 'Shipping', 'Payments', 'Notifications', 'Reviews', 'Digital downloads', 'License keys', 'Kanban', 'Satellites' ] )
        ->assertSeeHtml( 'aria-current="page"' )
        ->assertSee( 'Save settings' );
} );

it( 'is denied without settings.view', function (): void {
    Gate::define( 'ecommerce.settings.view', static fn (): bool => false );

    Livewire::test( Show::class, [ 'group' => 'general' ] )->assertForbidden();
} );

it( 'returns 404 for an unknown group', function (): void {
    Livewire::test( Show::class, [ 'group' => 'nope' ] )->assertNotFound();
} );

it( 'is read-only without settings.update', function (): void {
    Gate::define( 'ecommerce.settings.update', static fn (): bool => false );

    Livewire::test( Show::class, [ 'group' => 'checkout' ] )
        ->assertOk()
        ->assertDontSee( 'Save settings' )
        ->assertSeeHtml( 'disabled' )
        ->set( 'form.checkout__reservation_ttl_minutes', '30' )
        ->call( 'save' )
        ->assertForbidden();

    expect( settingsRepository()->isStored( 'checkout.reservation_ttl_minutes' ) )->toBeFalse();
} );

it( 'saves changed values through the engine repository', function (): void {
    $component = Livewire::test( Show::class, [ 'group' => 'general' ] )
        ->set( 'form.notifications__store_name', 'Acme Outfitters' )
        ->set( 'form.notifications__support_email', 'help@acme.test' )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSeeHtml( 'data-setting-stored' );

    expect( sentToasts( $component ) )->toContain( '2 settings saved.' )
        ->and( settingsRepository()->get( 'notifications.store_name' ) )->toBe( 'Acme Outfitters' )
        ->and( config( 'artisanpack.ecommerce.notifications.support_email' ) )->toBe( 'help@acme.test' );
} );

it( 'puts validation failures on their fields', function (): void {
    Livewire::test( Show::class, [ 'group' => 'checkout' ] )
        ->set( 'form.checkout__reservation_ttl_minutes', '0' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.checkout__reservation_ttl_minutes' ] );

    Livewire::test( Show::class, [ 'group' => 'general' ] )
        ->set( 'form.notifications__support_email', 'not-an-email' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.notifications__support_email' ] );

    expect( settingsRepository()->stored() )->toBe( [] );
} );

it( 'warns before changing the base currency and keeps historical orders', function (): void {
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    settingsRepository()->refresh();
    $order = Order::factory()->create( [ 'base_currency' => 'USD', 'fx_rate_to_base_e8' => 100_000_000 ] );

    $component = Livewire::test( Show::class, [ 'group' => 'general' ] )
        ->set( 'form.base_currency', 'eur' )
        ->call( 'save' )
        ->assertSet( 'confirmingBaseCurrency', true )
        ->assertSee( 'The base currency is changing from USD to EUR.' )
        ->assertSee( 'Existing orders keep the base currency and exchange rate they were placed with.' );

    expect( settingsRepository()->get( 'base_currency' ) )->toBe( 'USD' );

    $component->call( 'confirmBaseCurrencyChange' )
        ->assertSet( 'confirmingBaseCurrency', false )
        ->assertHasNoErrors();

    expect( settingsRepository()->get( 'base_currency' ) )->toBe( 'EUR' )
        ->and( $order->refresh()->base_currency )->toBe( 'USD' );
} );

it( 'resets a changed value to its default', function (): void {
    config()->set( 'artisanpack.ecommerce.reviews.allow_guests', true );
    settingsRepository()->refresh();
    settingsRepository()->update( 'reviews', [ 'reviews.allow_guests' => false ] );

    Livewire::test( Show::class, [ 'group' => 'reviews' ] )
        ->assertSet( 'form.reviews__allow_guests', false )
        ->assertSee( 'Reset to default' )
        ->call( 'resetToDefault', 'reviews.allow_guests' )
        ->assertSet( 'form.reviews__allow_guests', true )
        ->assertDontSee( 'Reset to default' );

    expect( settingsRepository()->isStored( 'reviews.allow_guests' ) )->toBeFalse();
} );

it( 'shows whether each payment credential is configured without showing it', function (): void {
    config()->set( 'artisanpack.ecommerce.gateways.stripe.secret_key', 'sk_test_hidden' );
    config()->set( 'artisanpack.ecommerce.gateways.stripe.webhook_secret', null );

    Livewire::test( Show::class, [ 'group' => 'payments' ] )
        ->assertSee( 'Enable Stripe' )
        ->assertSeeHtml( 'data-secret="artisanpack.ecommerce.gateways.stripe.secret_key" data-configured="yes"' )
        ->assertSeeHtml( 'data-secret="artisanpack.ecommerce.gateways.stripe.webhook_secret" data-configured="no"' )
        ->assertSee( 'ECOMMERCE_STRIPE_SECRET_KEY' )
        ->assertDontSee( 'sk_test_hidden' )
        ->assertSee( 'No payment gateway is registered.' );
} );

it( 'edits list and key/value settings', function (): void {
    Livewire::test( Show::class, [ 'group' => 'notifications' ] )
        ->set( 'form.notifications__admin_emails', "ops@acme.test\n\nowner@acme.test" )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( settingsRepository()->get( 'notifications.admin_emails' ) )->toBe( [ 'ops@acme.test', 'owner@acme.test' ] );

    Livewire::test( Show::class, [ 'group' => 'notifications' ] )
        ->set( 'form.notifications__admin_emails', "ops@acme.test\nnope" )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.notifications__admin_emails' ] );

    Livewire::test( Show::class, [ 'group' => 'tax' ] )
        ->call( 'addMapRow', 'localization.tax_labels' )
        ->set( 'form.localization__tax_labels.0.key', 'en_US' )
        ->set( 'form.localization__tax_labels.0.value', 'Sales Tax' )
        ->call( 'addMapRow', 'localization.tax_labels' )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertCount( 'form.localization__tax_labels', 1 )
        ->call( 'removeMapRow', 'localization.tax_labels', 0 )
        ->call( 'save' );

    expect( settingsRepository()->get( 'localization.tax_labels' ) )->toBe( [] );
} );

it( 'lists the registered satellites read-only', function (): void {
    Livewire::test( Show::class, [ 'group' => 'satellites' ] )
        ->assertOk()
        ->assertSeeHtml( 'data-satellite="' . EcommerceAdminLivewireServiceProvider::PACKAGE_NAME . '"' )
        ->assertSee( EcommerceAdminLivewireServiceProvider::VERSION )
        ->assertSee( 'Active' )
        ->assertSee( 'Not verified' )
        ->assertDontSee( 'Save settings' );
} );

it( 'mounts a satellite tab from the settings-tab registry', function (): void {
    Livewire::component( 'loyalty-settings-tab', LoyaltySettingsTab::class );
    app( SettingsTabRegistry::class )->register( 'loyalty', static fn (): string => 'Loyalty', 'loyalty-settings-tab', 65 );

    Livewire::test( Show::class, [ 'group' => 'loyalty' ] )
        ->assertOk()
        ->assertSeeInOrder( [ 'Notifications', 'Loyalty', 'Reviews' ] )
        ->assertSeeHtml( 'data-loyalty-settings' );
} );

it( 'serves the settings page', function (): void {
    $this->get( route( 'artisanpack.ecommerce.admin.settings.show', [ 'group' => 'general' ] ) )
        ->assertOk()
        ->assertSeeLivewire( Show::class );
} );

it( 're-checks settings.view on every update', function (): void {
    $component = Livewire::test( Show::class, [ 'group' => 'checkout' ] )->assertOk();

    Gate::define( 'ecommerce.settings.view', static fn (): bool => false );

    $component->set( 'confirmingBaseCurrency', false )->assertForbidden();
} );
