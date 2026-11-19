<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\NotificationTemplate;
use ArtisanPackUI\Ecommerce\Services\NotificationTemplateService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notifications\Edit;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'notificationTemplate.viewAny', 'notificationTemplate.view', 'notificationTemplate.update' ] );
    $this->actingAs( makeUser( [ 'email' => 'admin@example.test' ] ) );
} );

/**
 * An SMS template with no subject.
 */
function smsTemplate( string $body = 'Order {{ Order.number }} is confirmed.' ): NotificationTemplate
{
    return NotificationTemplate::factory()->create( [ 'channel' => 'sms', 'subject' => null, 'body' => $body ] );
}

it( 'lays the form out beside the variables and preview cards', function (): void {
    $template = NotificationTemplate::factory()->create( [ 'key' => 'order.confirmation' ] );

    $html = Livewire::test( Edit::class, [ 'template' => $template->id ] )->html();

    expect( $html )
        ->toContain( 'ec-notif-edit-split' )
        ->toContain( '<bdi dir="ltr" class="ec-mono">order.confirmation</bdi> · Email' )
        ->toContain( 'id="notification-variables-title"' )
        ->toContain( '<h2 data-scope="card" data-part="title" id="notification-preview-title">' )
        ->toContain( 'class="apui-badge ec-notif-edit-var"' )
        ->toContain( 'aria-label="Insert Order.number"' )
        ->toContain( 'data-preview-status' )
        ->toContain( 'class="ec-notif-edit-email"' )
        ->toMatch( '/class="apui-toggle"[^>]*data-state="checked"/' )
        ->not->toContain( 'daisy' )
        ->not->toContain( 'rounded-box' )
        ->not->toContain( 'border-base-300' );
} );

it( 'keeps the code editors left to right', function (): void {
    $template = NotificationTemplate::factory()->create();

    $html = Livewire::test( Edit::class, [ 'template' => $template->id ] )->html();

    expect( $html )->toMatch( '/data-part="body"\s+id="notification-body-body"[^>]*\s+dir="ltr"/' )
        ->and( $html )->toMatch( '/data-part="body"\s+id="notification-preview-data-body"[^>]*\s+dir="ltr"/' );
} );

it( 'marks the line of a template error, dims the preview and blocks the save', function (): void {
    $template = NotificationTemplate::factory()->create();

    $component = Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'body', "<p>Hi</p>\n<p>{{ Order.custmer.name }}</p>" );

    $html = $component->html();

    expect( $component->get( 'previewErrors' )[0]['line'] )->toBe( 2 )
        ->and( $html )->toMatch( '/data-part="line"[^>]*data-severity="error"[^>]*>2</' )
        ->and( $html )->toContain( 'ec-notif-edit-email ec-notif-edit-stale' )
        ->and( $html )->toContain( 'data-preview-errors' )
        ->and( $html )->not->toContain( 'data-preview-status' )
        ->and( $html )->toMatch( '/<button[^>]*type="submit"[^>]*disabled/' );
} );

it( 'lists every template error, not only the first in each field', function (): void {
    $template = NotificationTemplate::factory()->create();

    $component = Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'body', "<p>{{ Order.aaa }}</p>\n<p>{{ Order.bbb }}</p>" );

    $errors = $component->get( 'previewErrors' );
    $list   = Illuminate\Support\Str::betweenFirst( $component->html(), 'data-preview-error-list>', '</ul>' );

    expect( $errors )->toHaveCount( 2 )
        ->and( substr_count( $list, '<li' ) )->toBe( 2 )
        ->and( $list )->toContain( e( $errors[0]['message'] ) )
        ->and( $list )->toContain( e( $errors[1]['message'] ) );
} );

it( 'shows the error summary only after a failed save', function (): void {
    $template = NotificationTemplate::factory()->create();

    $component = Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'body', '<p>{{ Order.custmer.name }}</p>' )
        ->assertHasErrors( [ 'body' ] )
        ->assertDontSeeHtml( 'data-error-summary' );

    $component->set( 'body', '' )
        ->call( 'save' )
        ->assertHasErrors( [ 'body' => 'required' ] )
        ->assertSet( 'saveFailed', true )
        ->assertSeeHtml( 'data-error-summary' );

    $component->set( 'body', '<p>Fixed</p>' )
        ->assertSet( 'saveFailed', false )
        ->assertDontSeeHtml( 'data-error-summary' );
} );

it( 'clears the failed save once a save goes through', function (): void {
    $template = NotificationTemplate::factory()->create();

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'body', '' )
        ->call( 'save' )
        ->assertSet( 'saveFailed', true )
        ->set( 'body', '<p>Hello again</p>' )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'saveFailed', false );
} );

it( 'gives SMS no subject and no test send, with its length and a bubble preview', function (): void {
    $template = smsTemplate();

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->assertDontSeeHtml( 'id="notification-subject"' )
        ->assertDontSeeHtml( 'data-send-test' )
        ->assertDontSeeHtml( 'sandbox=""' )
        ->assertSeeHtml( 'data-preview-sms' )
        ->assertSeeHtml( 'Order A1B2C3D4 is confirmed.' )
        ->assertSeeHtml( '<bdi dir="ltr">28 / 160</bdi> characters · 1 message' )
        ->assertSee( 'SMS preview' );
} );

it( 'counts SMS messages as the text grows', function (): void {
    $template = smsTemplate();

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'body', str_repeat( 'a', 200 ) )
        ->assertSeeHtml( '<bdi dir="ltr">200 / 306</bdi> characters · 2 messages' );
} );

it( 'splits SMS text into GSM and UCS-2 messages', function ( string $text, array $expected ): void {
    expect( Edit::smsSegments( $text ) )->toBe( $expected );
} )->with( [
    'empty'           => [ '', [ 'characters' => 0, 'limit' => 160, 'messages' => 1 ] ],
    'one message'     => [ str_repeat( 'a', 160 ), [ 'characters' => 160, 'limit' => 160, 'messages' => 1 ] ],
    'two messages'    => [ str_repeat( 'a', 161 ), [ 'characters' => 161, 'limit' => 306, 'messages' => 2 ] ],
    'extension chars' => [ '€[]', [ 'characters' => 6, 'limit' => 160, 'messages' => 1 ] ],
    'unicode'         => [ 'Hello 👋', [ 'characters' => 8, 'limit' => 70, 'messages' => 1 ] ],
    'long unicode'    => [ str_repeat( 'é', 150 ) . 'ж', [ 'characters' => 151, 'limit' => 201, 'messages' => 3 ] ],
    'escape boundary' => [ str_repeat( '^', 153 ), [ 'characters' => 306, 'limit' => 459, 'messages' => 3 ] ],
    'emoji boundary'  => [ str_repeat( 'a', 66 ) . str_repeat( '👋', 34 ), [ 'characters' => 134, 'limit' => 201, 'messages' => 3 ] ],
] );

it( 'reads the line from a template error message', function ( string $message, ?int $line ): void {
    expect( Edit::errorLine( $message ) )->toBe( $line );
} )->with( [
    'twig error'          => [ 'Line 9: Unexpected token.', 9 ],
    'undeclared variable' => [ 'Line 2: "Order.x" is not an available variable for this template.', 2 ],
    'no line'             => [ 'The range operator (..) is not allowed in notification templates.', null ],
] );

it( 'builds diagnostics only for errors with a line in the field', function (): void {
    $errors = [
        [ 'field' => 'body', 'message' => 'Line 3: Oops.', 'line' => 3 ],
        [ 'field' => 'body', 'message' => 'No line.', 'line' => null ],
        [ 'field' => 'subject', 'message' => 'Line 1: Subject.', 'line' => 1 ],
    ];

    expect( Edit::diagnosticsFor( $errors, 'body' ) )->toBe( [
        [ 'line' => 3, 'message' => 'Line 3: Oops.', 'severity' => 'error' ],
    ] );
} );

it( 'confirms a reset in an alert dialog with the safe choice focused', function (): void {
    app( NotificationTemplateService::class )->sync();
    $template = NotificationTemplate::query()->where( 'channel', 'mail' )->firstOrFail();

    $html = Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->call( 'confirmReset' )
        ->html();

    expect( $html )
        ->toContain( 'role="alertdialog"' )
        ->toMatch( '/wire:click="cancelReset"[^>]*data-autofocus/' )
        ->toContain( 'Keep my copy' );
} );

it( 'shows read-only code editors to a user who can only view', function (): void {
    $template = NotificationTemplate::factory()->create();
    Gate::define( 'ecommerce.notificationTemplate.update', static fn (): bool => false );

    $html = Livewire::test( Edit::class, [ 'template' => $template->id ] )->html();

    expect( $html )
        ->toContain( 'data-body-readonly' )
        ->toContain( 'data-preview-data-readonly' )
        ->toMatch( '/id="notification-body"[^>]*readonly/' )
        ->not->toContain( 'Save template' );
} );
