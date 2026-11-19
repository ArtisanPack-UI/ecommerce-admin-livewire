<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\WebhookSubscription;
use ArtisanPackUI\Ecommerce\Webhooks\WebhookUrlGuard;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Webhooks\Index;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    WebhookUrlGuard::resolveUsing( static fn ( string $host ): array => [ '93.184.216.34' ] );
    grantAbilities( [ 'webhookSubscription.viewAny', 'webhookSubscription.create', 'webhookSubscription.update', 'webhookSubscription.delete' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    WebhookUrlGuard::resolveUsing( null );
} );

/**
 * The HTML of the table row holding the given text.
 */
function webhooksRowHtml( string $html, string $text ): string
{
    preg_match_all( '/<tr\b.*?<\/tr>/s', explode( '<tbody', $html )[1] ?? '', $rows );

    foreach ( $rows[0] as $row ) {
        if ( str_contains( $row, '>' . $text . '<' ) ) {
            return $row;
        }
    }

    return '';
}

it( 'keeps the table to seven columns with the URL under the name in mono', function (): void {
    WebhookSubscription::factory()->create( [ 'name' => 'Fulfillment sync', 'url' => 'https://ops.example.com/hooks/orders', 'events' => [ 'order.paid' ] ] );

    $html = Livewire::test( Index::class )->html();

    $head = explode( '</thead>', explode( '<thead', $html )[1] )[0];

    expect( substr_count( $head, 'data-part="head-cell"' ) )->toBe( 7 )
        ->and( $head )->not->toContain( '>URL<' )
        ->and( $head )->toContain( 'Failures' )
        ->and( $head )->toMatch( '/<span class="apui-sr-only">Actions<\/span>/' );

    $row = webhooksRowHtml( $html, 'Fulfillment sync' );

    expect( $row )->toMatch( '/data-part="primary">\s*(<!--.*?-->\s*)*<a [^>]*class="apui-link ec-webhooks-name"[^>]*>Fulfillment sync<\/a>/' )
        ->and( $row )->toMatch( '/data-part="secondary">\s*<bdi dir="ltr" class="ec-mono ec-webhooks-url" title="https:\/\/ops.example.com\/hooks\/orders"/' );
} );

it( 'keeps the URL and failure count in the CSV export', function (): void {
    WebhookSubscription::factory()->create( [ 'name' => 'Fulfillment sync', 'url' => 'https://ops.example.com/hooks/orders', 'events' => [ 'order.paid' ], 'consecutive_failures' => 4 ] );

    $component = Livewire::test( Index::class )->call( 'exportCsv' );
    $content   = base64_decode( (string) ( $component->effects['download']['content'] ?? '' ) );

    expect( $content )->toContain( 'URL' )
        ->and( $content )->toContain( 'https://ops.example.com/hooks/orders' )
        ->and( $content )->toContain( 'Failures' )
        ->and( $content )->not->toContain( 'Actions' );
} );

it( 'shows two event badges then a count of the rest', function (): void {
    WebhookSubscription::factory()->create( [ 'name' => 'Three events', 'events' => [ 'order.paid', 'order.refunded', 'order.cancelled' ] ] );

    $row = webhooksRowHtml( Livewire::test( Index::class )->html(), 'Three events' );

    expect( substr_count( $row, 'data-webhook-event=' ) )->toBe( 2 )
        ->and( $row )->toContain( '<bdi dir="ltr" class="ec-webhooks-event-key">order.paid</bdi>' )
        ->and( $row )->toContain( 'order.refunded' )
        ->and( $row )->toMatch( '/title="order.cancelled" data-webhook-more-events>\+1 more</' );
} );

it( 'shows All events as one primary badge with a zap', function (): void {
    WebhookSubscription::factory()->create( [ 'name' => 'Everything', 'events' => [ '*' ] ] );

    $row = webhooksRowHtml( Livewire::test( Index::class )->html(), 'Everything' );

    expect( $row )->toMatch( '/class="apui-badge"[^>]*data-webhook-all-events[^>]*data-color="primary"/' )
        ->and( $row )->toContain( 'All events' )
        ->and( $row )->not->toContain( 'data-webhook-event=' );
} );

it( 'reads Disabled as a warning and the failure count in red only when not zero', function (): void {
    WebhookSubscription::factory()->create( [ 'name' => 'Healthy hook', 'events' => [ 'order.paid' ], 'consecutive_failures' => 0 ] );
    WebhookSubscription::factory()->create( [ 'name' => 'Flaky hook', 'events' => [ 'order.paid' ], 'consecutive_failures' => 3 ] );
    WebhookSubscription::factory()->inactive()->create( [ 'name' => 'Broken hook', 'events' => [ 'order.paid' ], 'consecutive_failures' => 15 ] );

    $html = Livewire::test( Index::class )->html();

    $healthy = webhooksRowHtml( $html, 'Healthy hook' );
    $flaky   = webhooksRowHtml( $html, 'Flaky hook' );
    $broken  = webhooksRowHtml( $html, 'Broken hook' );

    expect( $healthy )->toMatch( '/data-webhook-state="active"[^>]*data-color="success"/' )
        ->and( $healthy )->toContain( '<bdi dir="ltr" class="ec-webhooks-failures ec-muted" data-webhook-failures>0</bdi>' )
        ->and( $flaky )->toContain( '<bdi dir="ltr" class="ec-webhooks-failures" data-failing data-webhook-failures>3</bdi>' )
        ->and( $broken )->toMatch( '/data-webhook-state="disabled"[^>]*data-color="warning"/' )
        ->and( $broken )->toContain( 'Subscription state:' );
} );

it( 'gives a disabled row a visible Re-enable button and names every icon button', function (): void {
    WebhookSubscription::factory()->create( [ 'name' => 'Live hook', 'events' => [ 'order.paid' ] ] );
    $broken = WebhookSubscription::factory()->inactive()->create( [ 'name' => 'Broken hook', 'events' => [ 'order.paid' ] ] );

    $html = Livewire::test( Index::class )->html();

    $live       = webhooksRowHtml( $html, 'Live hook' );
    $brokenHtml = webhooksRowHtml( $html, 'Broken hook' );

    expect( $live )->not->toContain( 'data-re-enable' )
        ->and( $brokenHtml )->toMatch( '/aria-label="Re-enable Broken hook"[^>]*data-re-enable="' . $broken->id . '"/' )
        ->and( $brokenHtml )->toMatch( '/data-re-enable="' . $broken->id . '"[^>]*>.*Re-enable<\/span>/s' )
        ->and( $live )->toContain( 'aria-label="Rotate the secret of Live hook"' )
        ->and( $live )->toContain( 'aria-label="Edit Live hook"' )
        ->and( $live )->toContain( 'aria-label="Delete Live hook"' );
} );

it( 'offers New subscription from the empty state only to people who may create', function (): void {
    Livewire::test( Index::class )
        ->assertSee( 'No webhook subscriptions yet' )
        ->assertSeeHtml( 'data-empty-new-webhook' );

    Gate::define( 'ecommerce.webhookSubscription.create', static fn (): bool => false );

    Livewire::test( Index::class )
        ->assertSee( 'No webhook subscriptions yet' )
        ->assertDontSeeHtml( 'data-empty-new-webhook' );
} );

it( 'picks events from a checkbox list with All events first', function (): void {
    $html = Livewire::test( Index::class )->call( 'create' )->html();

    $items = substr( $html, (int) strpos( $html, 'data-scope="checkbox-group" data-part="items"' ) );

    expect( $html )->toMatch( '/<fieldset[^>]*class="[^"]*apui-checkbox-group[^"]*ec-webhooks-event-list/' )
        ->and( $items )->toMatch( '/^[^>]*>\s*(<!--.*?-->\s*)*<div class="apui-checkbox"[^>]*>\s*<span[^>]*>\s*<input[^>]*value="\*"/s' )
        ->and( $items )->toContain( 'value="order.placed"' )
        ->and( $html )->toContain( 'data-secret-note' );
} );

it( 'marks the URL field invalid when the engine rejects it', function (): void {
    $html = Livewire::test( Index::class )
        ->call( 'create' )
        ->set( 'form.name', 'Internal' )
        ->set( 'form.url', 'http://10.0.0.12/hooks' )
        ->set( 'form.events', [ 'order.paid' ] )
        ->call( 'save' )
        ->assertHasErrors( 'form.url' )
        ->html();

    expect( $html )->toMatch( '/id="webhook-url"[^>]*aria-invalid="true"/' );
} );

it( 'hides the secret note when editing', function (): void {
    $subscription = WebhookSubscription::factory()->create( [ 'events' => [ 'order.paid' ] ] );

    Livewire::test( Index::class )
        ->call( 'edit', $subscription->id )
        ->assertSee( 'Edit subscription' )
        ->assertDontSeeHtml( 'data-secret-note' );
} );

it( 'confirms a delete in an alertdialog that opens on keeping the subscription', function (): void {
    $subscription = WebhookSubscription::factory()->create( [ 'name' => 'Inventory mirror', 'events' => [ 'order.paid' ] ] );

    $html = Livewire::test( Index::class )->call( 'confirmDelete', $subscription->id )->html();

    expect( $html )->toMatch( '/role="alertdialog"[^>]*>.*data-delete-webhook/s' )
        ->and( $html )->toMatch( '/data-autofocus[^>]*>\s*(<[^>]+>\s*)*Keep subscription/' )
        ->and( $html )->toContain( 'Delete &quot;Inventory mirror&quot;?' );
} );

it( 'opens the edit drawer from ?edit= and clears it when the drawer closes', function (): void {
    $subscription = WebhookSubscription::factory()->create( [ 'name' => 'Fulfillment sync', 'url' => 'https://ops.example.com/hooks/orders', 'events' => [ 'order.paid' ] ] );

    Livewire::withQueryParams( [ 'edit' => (string) $subscription->id ] )
        ->test( Index::class )
        ->assertSet( 'editing', true )
        ->assertSet( 'subscriptionId', $subscription->id )
        ->assertSet( 'form.url', 'https://ops.example.com/hooks/orders' )
        ->assertSet( 'editFromUrl', (string) $subscription->id )
        ->assertSee( 'Edit subscription' )
        ->set( 'editing', false )
        ->assertSet( 'editFromUrl', '' );
} );

it( 'closes the drawer on the server however it is dismissed, so ?edit= does not linger', function (): void {
    $subscription = WebhookSubscription::factory()->create( [ 'events' => [ 'order.paid' ] ] );

    $html = Livewire::withQueryParams( [ 'edit' => (string) $subscription->id ] )->test( Index::class )->html();

    // The X, Escape and the backdrop only change the entangled property, so it must sync live.
    expect( $html )->toMatch( '/entangle\((?:\'|&#0?39;|&apos;)editing(?:\'|&#0?39;|&apos;)\)\.live/' );
} );

it( 'keeps ?edit= on the subscription that is open', function (): void {
    $first  = WebhookSubscription::factory()->create( [ 'events' => [ 'order.paid' ] ] );
    $second = WebhookSubscription::factory()->create( [ 'events' => [ 'order.paid' ] ] );

    Livewire::withQueryParams( [ 'edit' => (string) $first->id ] )
        ->test( Index::class )
        ->assertSet( 'editFromUrl', (string) $first->id )
        ->call( 'edit', $second->id )
        ->assertSet( 'editFromUrl', (string) $second->id )
        ->call( 'create' )
        ->assertSet( 'editFromUrl', '' );
} );

it( 'ignores an ?edit= id that is unknown, not a number, or not editable', function ( string $edit, bool $canUpdate ): void {
    WebhookSubscription::factory()->create( [ 'id' => 7, 'events' => [ 'order.paid' ] ] );

    if ( ! $canUpdate ) {
        Gate::define( 'ecommerce.webhookSubscription.update', static fn (): bool => false );
    }

    Livewire::withQueryParams( [ 'edit' => $edit ] )
        ->test( Index::class )
        ->assertOk()
        ->assertSet( 'editing', false )
        ->assertSet( 'subscriptionId', null )
        ->assertSet( 'editFromUrl', '' );
} )->with( [
    'unknown'      => [ '999', true ],
    'not a number' => [ 'abc', true ],
    'negative'     => [ '-7', true ],
    'not editable' => [ '7', false ],
] );
