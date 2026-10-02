<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Mail\NotificationTemplateMail;
use ArtisanPackUI\Ecommerce\Models\NotificationTemplate;
use ArtisanPackUI\Ecommerce\Notifications\NotificationCatalog;
use ArtisanPackUI\Ecommerce\Services\NotificationTemplateService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notifications\Edit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'notificationTemplate.viewAny', 'notificationTemplate.view', 'notificationTemplate.update' ] );
    $this->user = makeUser( [ 'email' => 'admin@example.test' ] );
    $this->actingAs( $this->user );
} );

/**
 * A synced catalog row.
 */
function catalogTemplate( string $key = NotificationCatalog::ORDER_CONFIRMATION ): NotificationTemplate
{
    app( NotificationTemplateService::class )->sync();

    return NotificationTemplate::query()->where( 'key', $key )->firstOrFail();
}

it( 'renders the editor with a preview', function (): void {
    $template = NotificationTemplate::factory()->create();

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->assertOk()
        ->assertSet( 'subject', 'Order {{ Order.number }}' )
        ->assertSet( 'previewSubject', 'Order A1B2C3D4' )
        ->assertSeeHtml( 'sandbox=""' )
        ->assertSeeHtml( 'title="Email preview"' )
        ->assertSee( 'Order.customer.name' )
        ->assertSet( 'previewErrors', [] );
} );

it( 'is denied without notificationTemplate.view', function (): void {
    $template = NotificationTemplate::factory()->create();
    Gate::define( 'ecommerce.notificationTemplate.view', static fn (): bool => false );

    Livewire::test( Edit::class, [ 'template' => $template->id ] )->assertForbidden();
} );

it( 'returns not found for a missing template', function (): void {
    Livewire::test( Edit::class, [ 'template' => 9999 ] )->assertNotFound();
} );

it( 'returns not found when the template is deleted mid-session', function (): void {
    $template = NotificationTemplate::factory()->create();

    $component = Livewire::test( Edit::class, [ 'template' => $template->id ] );

    $template->delete();

    $component->call( 'save' )->assertNotFound();
} );

it( 'updates the preview live and saves', function (): void {
    $template = NotificationTemplate::factory()->create();

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'subject', 'Thanks, {{ Order.customer.name }}' )
        ->assertSet( 'previewSubject', 'Thanks, Ada' )
        ->set( 'previewJson', '{"Order": {"number": "X1", "customer": {"name": "Grace"}}}' )
        ->assertSet( 'previewSubject', 'Thanks, Grace' )
        ->call( 'save' )
        ->assertHasNoErrors();

    $template->refresh();
    expect( $template->subject )->toBe( 'Thanks, {{ Order.customer.name }}' )
        ->and( $template->preview_data['Order']['customer']['name'] )->toBe( 'Grace' );
} );

it( 'shows an undeclared variable with its line and blocks the save', function (): void {
    $template = NotificationTemplate::factory()->create();

    $component = Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'body', "<p>Hi</p>\n<p>{{ Order.custmer.name }}</p>" )
        ->assertHasErrors( [ 'body' ] )
        ->assertSee( 'Line 2' )
        ->assertSeeHtml( 'data-preview-errors' );

    expect( $component->get( 'previewErrors' ) )->toHaveCount( 1 );

    $component->call( 'save' )->assertHasErrors( [ 'body' ] );

    expect( $template->refresh()->body )->toBe( '<p>Hello {{ Order.customer.name }}</p>' );
} );

it( 'reports a sandbox error inline', function (): void {
    $template = NotificationTemplate::factory()->create();

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'body', '{{ include("secret.twig") }}' )
        ->assertHasErrors( [ 'body' ] )
        ->call( 'save' )
        ->assertHasErrors( [ 'body' ] );

    expect( $template->refresh()->body )->toBe( '<p>Hello {{ Order.customer.name }}</p>' );
} );

it( 'rejects preview data that is not a JSON object', function ( string $json ): void {
    $template = NotificationTemplate::factory()->create();

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'previewJson', $json )
        ->assertHasErrors( [ 'previewJson' ] )
        ->call( 'save' )
        ->assertHasErrors( [ 'previewJson' ] );
} )->with( [ 'broken' => '{"Order": ', 'list' => '[1, 2]', 'scalar' => '"text"' ] );

it( 'requires a body', function (): void {
    $template = NotificationTemplate::factory()->create();

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'body', '' )
        ->call( 'save' )
        ->assertHasErrors( [ 'body' => 'required' ] );
} );

it( 'resets to the default copy after confirmation', function (): void {
    $template = catalogTemplate();
    app( NotificationTemplateService::class )->update( $template, [ 'subject' => 'Custom', 'body' => '<p>Custom</p>' ] );
    $default = $template->definition();

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->call( 'confirmReset' )
        ->assertSet( 'confirmingReset', true )
        ->call( 'resetToDefault' )
        ->assertSet( 'confirmingReset', false )
        ->assertSet( 'body', $default->defaultBody() );

    expect( $template->refresh() )
        ->subject->toBe( $default->defaultSubject() )
        ->body->toBe( $default->defaultBody() );
} );

it( 'has no reset for a template without a default', function (): void {
    $template = NotificationTemplate::factory()->create();

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->assertDontSee( 'Reset to default' )
        ->call( 'confirmReset' )
        ->assertSet( 'confirmingReset', false );
} );

it( 'adds a translation copied from the current one', function (): void {
    $template = NotificationTemplate::factory()->create( [ 'key' => 'test.welcome' ] );

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'newLocale', 'de' )
        ->call( 'addLocale' )
        ->assertHasNoErrors()
        ->assertRedirect();

    $copy = NotificationTemplate::query()->where( 'key', 'test.welcome' )->where( 'locale', 'de' )->firstOrFail();
    expect( $copy->body )->toBe( $template->body );
} );

it( 'refuses a translation that already exists', function (): void {
    $template = NotificationTemplate::factory()->create( [ 'key' => 'test.welcome' ] );

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'newLocale', 'en' )
        ->call( 'addLocale' )
        ->assertHasErrors( [ 'newLocale' ] );
} );

it( 'switches to another translation', function (): void {
    $template = NotificationTemplate::factory()->create( [ 'key' => 'test.welcome' ] );
    $german   = NotificationTemplate::factory()->create( [ 'key' => 'test.welcome', 'locale' => 'de' ] );

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'localeId', $german->id )
        ->assertRedirect( route( 'artisanpack.ecommerce.admin.notifications.edit', [ 'template' => $german->id ] ) );
} );

it( 'turns the template off and on', function (): void {
    $template = NotificationTemplate::factory()->create();

    $component = Livewire::test( Edit::class, [ 'template' => $template->id ] )->set( 'isActive', false );
    expect( $template->refresh()->is_active )->toBeFalse();

    $component->set( 'isActive', true );
    expect( $template->refresh()->is_active )->toBeTrue();
} );

it( 'sends the unsaved preview as a test to the signed-in admin', function (): void {
    Mail::fake();
    $template = NotificationTemplate::factory()->create();

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'subject', 'Draft {{ Order.number }}' )
        ->call( 'sendTest' );

    Mail::assertSent( NotificationTemplateMail::class, static fn ( NotificationTemplateMail $mail ): bool => $mail->hasTo( 'admin@example.test' )
        && '[Test] Draft A1B2C3D4' === $mail->subjectLine
        && str_contains( $mail->htmlBody, 'Hello Ada' ) );

    expect( $template->refresh()->subject )->toBe( 'Order {{ Order.number }}' );
} );

it( 'refuses to send a test while the template has errors', function (): void {
    Mail::fake();
    $template = NotificationTemplate::factory()->create();

    $component = Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'body', '{{ Nope.value }}' )
        ->call( 'sendTest' );

    Mail::assertNothingSent();
    expect( sentToasts( $component ) )->toContain( 'The test was not sent.' );
} );

it( 'limits how many tests can be sent', function (): void {
    Mail::fake();
    $template  = NotificationTemplate::factory()->create();
    $component = Livewire::test( Edit::class, [ 'template' => $template->id ] );

    for ( $i = 0; $i < Edit::TEST_SENDS_PER_MINUTE + 1; $i++ ) {
        $component->call( 'sendTest' );
    }

    Mail::assertSentCount( Edit::TEST_SENDS_PER_MINUTE );
} );

it( 'hides the editing controls without notificationTemplate.update', function (): void {
    $template = NotificationTemplate::factory()->create();
    Gate::define( 'ecommerce.notificationTemplate.update', static fn (): bool => false );

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->assertOk()
        ->assertDontSee( 'Save template' )
        ->assertDontSeeHtml( 'data-add-locale' )
        ->call( 'save' )
        ->assertForbidden();
} );

it( 'refuses to toggle without notificationTemplate.update', function (): void {
    $template = NotificationTemplate::factory()->create();
    Gate::define( 'ecommerce.notificationTemplate.update', static fn (): bool => false );

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'isActive', false )
        ->assertForbidden();

    expect( $template->refresh()->is_active )->toBeTrue();
} );

it( 'builds insert snippets for list variables', function (): void {
    expect( Edit::variableSnippets( [ 'Order.number', 'Order.items.*.name' ] ) )->toBe( [
        [ 'path' => 'Order.number', 'snippet' => '{{ Order.number }}' ],
        [ 'path' => 'Order.items.*.name', 'snippet' => '{% for item in Order.items %}{{ item.name }}{% endfor %}' ],
    ] );
} );

it( 'escapes template markup inside the sandboxed preview iframe', function (): void {
    $template = NotificationTemplate::factory()->create();

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( 'body', '<p>Hi</p><script>alert(1)</script>' )
        ->assertSet( 'previewErrors', [] )
        ->assertSeeHtml( 'sandbox=""' )
        ->assertSeeHtml( 'srcdoc="&lt;p&gt;Hi&lt;/p&gt;&lt;script&gt;alert(1)&lt;/script&gt;"' )
        ->assertDontSeeHtml( '<script>alert(1)</script>' );
} );

it( 'caps the sources and preview data before rendering', function ( string $property, string $value, string $field ): void {
    $template = NotificationTemplate::factory()->create();

    $component = Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->set( $property, $value )
        ->assertHasErrors( [ $field ] );

    expect( $component->get( 'previewErrors' ) )->toHaveCount( 1 )
        ->and( $component->get( 'previewErrors' )[0]['field'] )->toBe( $field );

    $component->call( 'save' )->assertHasErrors( [ $field ] );

    expect( $template->refresh()->body )->not->toBe( $value );
} )->with( [
    'long subject'      => [ 'subject', str_repeat( 'a', Edit::MAX_SUBJECT + 1 ), 'subject' ],
    'long body'         => [ 'body', str_repeat( 'a', Edit::MAX_BODY + 1 ), 'body' ],
    'large preview'     => [ 'previewJson', '{"Pad": "' . str_repeat( 'a', Edit::MAX_PREVIEW_BYTES ) . '"}', 'previewJson' ],
    'long nested list'  => [ 'previewJson', '{"Order": {"items": [' . implode( ',', array_fill( 0, Edit::MAX_PREVIEW_ITEMS + 1, '0' ) ) . ']}}', 'previewJson' ],
] );

it( 'does not re-render edits from a user who can only view', function (): void {
    $template = NotificationTemplate::factory()->create();
    Gate::define( 'ecommerce.notificationTemplate.update', static fn (): bool => false );

    Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->assertSeeHtml( 'data-body-readonly' )
        ->assertDontSeeHtml( 'data-body-editor' )
        ->set( 'body', '{% for a in Order.items %}{% for b in Order.items %}x{% endfor %}{% endfor %}' )
        ->assertSet( 'body', (string) $template->body )
        ->set( 'subject', 'Changed {{ Order.number }}' )
        ->assertSet( 'subject', 'Order {{ Order.number }}' )
        ->assertSet( 'previewSubject', 'Order A1B2C3D4' );
} );

it( 'reports a failed test send instead of erroring', function (): void {
    $template = NotificationTemplate::factory()->create();
    Mail::shouldReceive( 'to' )->andThrow( new RuntimeException( 'SMTP down' ) );

    $component = Livewire::test( Edit::class, [ 'template' => $template->id ] )
        ->call( 'sendTest' )
        ->assertOk();

    expect( sentToasts( $component ) )->toContain( 'The test email could not be sent.' );
} );
