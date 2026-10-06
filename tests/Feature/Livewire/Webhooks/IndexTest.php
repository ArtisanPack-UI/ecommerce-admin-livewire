<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\WebhookDelivery;
use ArtisanPackUI\Ecommerce\Models\WebhookSubscription;
use ArtisanPackUI\Ecommerce\Webhooks\WebhookUrlGuard;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Webhooks\Index;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    WebhookUrlGuard::resolveUsing( static fn ( string $host ): array => 'internal.example.test' === $host ? [ '10.0.0.5' ] : [ '93.184.216.34' ] );
    grantAbilities( [ 'webhookSubscription.viewAny', 'webhookSubscription.create', 'webhookSubscription.update', 'webhookSubscription.delete' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    WebhookUrlGuard::resolveUsing( null );
} );

it( 'renders subscriptions with their state and failures', function (): void {
    WebhookSubscription::factory()->create( [ 'name' => 'Fulfilment partner', 'url' => 'https://partner.example.test/hooks', 'events' => [ 'order.refunded' ] ] );
    WebhookSubscription::factory()->inactive()->create( [ 'name' => 'Broken', 'events' => [ '*' ], 'consecutive_failures' => 10 ] );

    Livewire::test( Index::class )
        ->assertOk()
        ->assertSeeHtml( '<caption class="sr-only">Webhook subscriptions</caption>' )
        ->assertSee( 'Fulfilment partner' )
        ->assertSee( 'https://partner.example.test/hooks' )
        ->assertSee( 'order.refunded' )
        ->assertSee( 'All events' )
        ->assertSee( 'Disabled after 10 failures' );
} );

it( 'is denied without webhookSubscription.viewAny', function (): void {
    Gate::define( 'ecommerce.webhookSubscription.viewAny', static fn (): bool => false );

    Livewire::test( Index::class )->assertForbidden();
} );

it( 'filters by state and searches', function (): void {
    WebhookSubscription::factory()->create( [ 'name' => 'Live hook' ] );
    WebhookSubscription::factory()->inactive()->create( [ 'name' => 'Paused hook' ] );

    Livewire::test( Index::class )
        ->set( 'filters.active', '0' )
        ->assertSee( 'Paused hook' )
        ->assertDontSee( 'Live hook' )
        ->set( 'filters.active', '' )
        ->set( 'search', 'live' )
        ->assertSee( 'Live hook' )
        ->assertDontSee( 'Paused hook' );
} );

it( 'creates a subscription and shows its secret once', function (): void {
    $component = Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.name', 'Fulfilment' )
        ->set( 'form.url', 'https://partner.example.test/hooks' )
        ->set( 'form.events', [ 'order.refunded', 'order.cancelled' ] )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'editing', false )
        ->assertSet( 'showingSecret', true );

    $subscription = WebhookSubscription::query()->sole();
    $secret       = (string) $subscription->secret;

    expect( $secret )->toStartWith( 'whsec_' )
        ->and( $subscription->events )->toBe( [ 'order.refunded', 'order.cancelled' ] )
        ->and( (string) DB::table( ( new WebhookSubscription() )->getTable() )->value( 'secret' ) )->not->toContain( $secret );

    $component->assertSeeHtml( 'data-revealed-secret' )
        ->assertSee( $secret )
        ->call( 'dismissSecret' )
        ->assertSet( 'showingSecret', false )
        ->assertDontSee( $secret );
} );

it( 'forgets the secret when the notice is closed from the client', function (): void {
    Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.name', 'Partner' )
        ->set( 'form.url', 'https://partner.example.test/hooks' )
        ->set( 'form.events', [ '*' ] )
        ->call( 'save' )
        ->set( 'showingSecret', false )
        ->assertDontSeeHtml( 'data-revealed-secret' );
} );

it( 'keeps the revealed secret out of the component snapshot', function (): void {
    $subscription = WebhookSubscription::factory()->create();

    $component = Livewire::test( Index::class )->call( 'confirmRotate', $subscription->id );
    $component->call( 'rotateSecret', $component->viewData( 'rotateToken' ) );

    $secret = (string) $subscription->refresh()->secret;

    expect( $component->html() )->toContain( $secret )
        ->and( json_encode( $component->snapshot ) )->not->toContain( $secret );

    $component->call( '$refresh' );

    expect( json_encode( $component->snapshot ) )->not->toContain( $secret )
        ->and( $component->get( 'showingSecret' ) )->toBeTrue();
} );

it( 'surfaces the engine URL rules as validation errors', function ( string $url ): void {
    Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.name', 'Partner' )
        ->set( 'form.url', $url )
        ->set( 'form.events', [ 'order.refunded' ] )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.url' ] );

    expect( WebhookSubscription::query()->count() )->toBe( 0 );
} )->with( [
    'plain http'      => 'http://partner.example.test/hooks',
    'loopback ip'     => 'https://127.0.0.1/hooks',
    'private address' => 'https://internal.example.test/hooks',
    'not a url'       => 'partner',
] );

it( 'requires a name and at least one event', function (): void {
    Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.url', 'https://partner.example.test/hooks' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.name' => 'required', 'form.events' => 'required' ] );
} );

it( 'reports a create a store extension refused', function (): void {
    addFilter( 'ap.ecommerce.webhook.subscribing', static fn (): mixed => null );

    $component = Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.name', 'Partner' )
        ->set( 'form.url', 'https://partner.example.test/hooks' )
        ->set( 'form.events', [ 'order.refunded' ] )
        ->call( 'save' )
        ->assertSet( 'showingSecret', false );

    removeAllFilters( 'ap.ecommerce.webhook.subscribing' );

    expect( WebhookSubscription::query()->count() )->toBe( 0 )
        ->and( sentToasts( $component ) )->toContain( 'The subscription was not created.' );
} );

it( 'edits a subscription without touching its secret', function (): void {
    $subscription = WebhookSubscription::factory()->create( [ 'name' => 'Old', 'secret' => 'whsec_original-secret-value' ] );

    Livewire::test( Index::class )
        ->call( 'edit', $subscription->id )
        ->assertSet( 'form.name', 'Old' )
        ->set( 'form.name', 'New name' )
        ->set( 'form.events', [ '*' ] )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'showingSecret', false );

    $subscription->refresh();

    expect( $subscription->name )->toBe( 'New name' )
        ->and( $subscription->events )->toBe( [ '*' ] )
        ->and( $subscription->secret )->toBe( 'whsec_original-secret-value' );
} );

it( 'deletes a subscription and its deliveries after confirmation', function (): void {
    $subscription = WebhookSubscription::factory()->create( [ 'name' => 'Gone' ] );
    WebhookDelivery::factory()->create( [ 'subscription_id' => $subscription->id ] );

    $component = Livewire::test( Index::class )
        ->call( 'confirmDelete', $subscription->id )
        ->assertSet( 'confirmingDelete', true )
        ->assertSeeHtml( 'data-delete-webhook' );

    $token = $component->viewData( 'deleteToken' );

    $component->call( 'delete', $token )->assertSet( 'deletingId', null );

    expect( WebhookSubscription::query()->count() )->toBe( 0 )
        ->and( WebhookDelivery::query()->count() )->toBe( 0 );
} );

it( 'rotates the secret after confirmation and shows the new one once', function (): void {
    $subscription = WebhookSubscription::factory()->create( [ 'secret' => 'whsec_leaked-secret-value-0000' ] );

    $component = Livewire::test( Index::class )
        ->call( 'confirmRotate', $subscription->id )
        ->assertSeeHtml( 'data-rotate-secret' );

    $component->call( 'rotateSecret', $component->viewData( 'rotateToken' ) );

    $secret = (string) $subscription->refresh()->secret;

    expect( $secret )->not->toBe( 'whsec_leaked-secret-value-0000' )->toStartWith( 'whsec_' );

    $component->assertSee( $secret )->assertSet( 'showingSecret', true )->assertSet( 'confirmingRotate', false );
} );

it( 'does not rotate twice with the same token', function (): void {
    $subscription = WebhookSubscription::factory()->create();

    $component = Livewire::test( Index::class )->call( 'confirmRotate', $subscription->id );
    $token     = $component->viewData( 'rotateToken' );
    $component->call( 'rotateSecret', $token );
    $first = (string) $subscription->refresh()->secret;

    $component->call( 'confirmRotate', $subscription->id )->call( 'rotateSecret', $token );

    expect( (string) $subscription->refresh()->secret )->toBe( $first );
} );

it( 're-enables a subscription the engine disabled and resets its failures', function (): void {
    $subscription = WebhookSubscription::factory()->inactive()->create( [ 'consecutive_failures' => 10 ] );

    Livewire::test( Index::class )->call( 'reEnable', $subscription->id );

    $subscription->refresh();

    expect( $subscription->is_active )->toBeTrue()
        ->and( $subscription->consecutive_failures )->toBe( 0 );
} );

it( 'hides the write controls without the write abilities', function (): void {
    Gate::define( 'ecommerce.webhookSubscription.create', static fn (): bool => false );
    Gate::define( 'ecommerce.webhookSubscription.update', static fn (): bool => false );
    Gate::define( 'ecommerce.webhookSubscription.delete', static fn (): bool => false );
    $subscription = WebhookSubscription::factory()->inactive()->create();

    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'wire:click="create"' )
        ->assertDontSeeHtml( 'wire:click="edit(' )
        ->assertDontSeeHtml( 'wire:click="confirmRotate(' )
        ->assertDontSeeHtml( 'wire:click="reEnable(' )
        ->assertDontSeeHtml( 'wire:click="confirmDelete(' )
        ->call( 'edit', $subscription->id )
        ->assertForbidden();
} );

it( 'does not recreate a subscription deleted while it was being edited', function (): void {
    $subscription = WebhookSubscription::factory()->create( [ 'name' => 'Partner', 'url' => 'https://partner.example.test/hooks', 'events' => [ 'order.refunded' ] ] );

    $component = Livewire::test( Index::class )->call( 'edit', $subscription->id );

    $subscription->delete();

    $component->set( 'form.name', 'Partner again' )
        ->call( 'save' )
        ->assertSet( 'editing', false );

    expect( WebhookSubscription::query()->count() )->toBe( 0 )
        ->and( sentToasts( $component ) )->toContain( 'This subscription was deleted.' );
} );
