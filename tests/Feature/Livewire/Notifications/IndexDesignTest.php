<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\NotificationTemplate;
use ArtisanPackUI\Ecommerce\Notifications\NotificationCatalog;
use ArtisanPackUI\Ecommerce\Services\NotificationTemplateService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notifications\Index;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'notificationTemplate.viewAny', 'notificationTemplate.view', 'notificationTemplate.update' ] );
    $this->actingAs( makeUser() );
} );

it( 'renders one design-system card per notification with its key in mono', function (): void {
    NotificationTemplate::factory()->create( [ 'key' => 'test.welcome', 'channel' => 'mail', 'locale' => 'en' ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )
        ->toContain( 'class="apui-card"' )
        ->toContain( 'data-template-group="test.welcome"' )
        ->toContain( '<bdi dir="ltr" class="ec-mono ec-cell-muted" data-template-key>test.welcome</bdi>' )
        ->toContain( 'data-scope="card" data-part="title"' )
        ->not->toContain( 'table-sm' )
        ->not->toContain( 'opacity-75' );
} );

it( 'draws a channel icon beside each channel name', function (): void {
    NotificationTemplate::factory()->create( [ 'key' => 'test.sms', 'channel' => 'sms', 'locale' => 'en' ] );
    NotificationTemplate::factory()->create( [ 'key' => 'test.inapp', 'channel' => 'database', 'locale' => 'en' ] );

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-channel="sms"' )
        ->assertSeeHtml( 'data-channel="database"' )
        ->assertSee( 'SMS' )
        ->assertSee( 'In-app' );

    expect( Index::channelIcon( 'mail' ) )->toBe( 'mail' )
        ->and( Index::channelIcon( 'sms' ) )->toBe( 'message-square' )
        ->and( Index::channelIcon( 'database' ) )->toBe( 'bell' )
        ->and( Index::channelIcon( 'push' ) )->toBe( 'megaphone' );
} );

it( 'shows Off as a neutral badge and Active as a success badge', function (): void {
    NotificationTemplate::factory()->create( [ 'key' => 'test.off', 'is_active' => false ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )->toMatch( '/data-color="neutral"[^>]*data-template-state="off"|data-template-state="off"[^>]*data-color="neutral"/' )
        ->and( $html )->toMatch( '/data-color="success"[^>]*data-template-state="active"|data-template-state="active"[^>]*data-color="success"/' )
        ->and( $html )->not->toMatch( '/data-color="warning"[^>]*data-template-state/' );
} );

it( 'keeps a catalog row at Never until its copy differs from the shipped default', function (): void {
    Carbon::setTestNow( '2026-11-01 10:00:00' );
    app( NotificationTemplateService::class )->sync();
    $template   = NotificationTemplate::query()->where( 'key', NotificationCatalog::ORDER_CONFIRMATION )->firstOrFail();
    $definition = $template->definition();

    // A later sync re-saves the row when the definition's variables change; switching it off saves it too.
    Carbon::setTestNow( '2026-11-12 10:00:00' );
    $template->forceFill( [ 'variables' => [], 'is_active' => false ] )->save();

    expect( Index::wasEdited( $template->fresh() ) )->toBeFalse();

    $template->update( [ 'body' => $definition->defaultBody( $template->locale ) . "\n<p>Thanks!</p>" ] );

    expect( Index::wasEdited( $template->fresh() ) )->toBeTrue();

    $template->update( [ 'body' => str_replace( "\n", "\r\n", $definition->defaultBody( $template->locale ) ) ] );
    Carbon::setTestNow();

    // Reset to the shipped copy (line endings aside) reads Never again.
    expect( Index::wasEdited( $template->fresh() ) )->toBeFalse();
} );

it( 'shows Never for a row not saved since it was created, and the date once edited', function (): void {
    Carbon::setTestNow( '2026-11-01 10:00:00' );
    $untouched = NotificationTemplate::factory()->create( [ 'key' => 'test.untouched' ] );
    $edited    = NotificationTemplate::factory()->create( [ 'key' => 'test.edited' ] );

    Carbon::setTestNow( '2026-11-12 10:00:00' );
    $edited->update( [ 'subject' => 'Changed' ] );
    Carbon::setTestNow();

    expect( Index::wasEdited( $untouched->fresh() ) )->toBeFalse()
        ->and( Index::wasEdited( $edited->fresh() ) )->toBeTrue();

    Livewire::test( Index::class )
        ->assertSeeHtml( 'data-never-edited' )
        ->assertSeeHtml( 'datetime="' . $edited->fresh()->updated_at->toIso8601String() . '"' );
} );

it( 'gives each edit link a name that includes the channel and language', function (): void {
    NotificationTemplate::factory()->create( [ 'key' => 'test.welcome', 'channel' => 'mail', 'locale' => 'en' ] );
    NotificationTemplate::factory()->create( [ 'key' => 'test.welcome', 'channel' => 'sms', 'locale' => 'en' ] );

    $html = Livewire::test( Index::class )->html();

    expect( $html )
        ->toMatch( '/aria-label="Edit [^"]+ \\(Email, English \\(en\\)\\)"/' )
        ->toMatch( '/aria-label="Edit [^"]+ \\(SMS, English \\(en\\)\\)"/' );
} );

it( 'shows Clear filters only while a filter is set, and in the no-matches state', function (): void {
    Livewire::test( Index::class )
        ->assertDontSeeHtml( 'data-clear-filters' )
        ->set( 'channel', 'mail' )
        ->assertSeeHtml( 'data-clear-filters' )
        ->assertDontSeeHtml( 'data-empty-clear-filters' )
        ->set( 'channel', 'nope' )
        ->assertSee( 'No matches' )
        ->assertSeeHtml( 'data-empty-clear-filters' )
        ->call( 'resetFilters' )
        ->assertDontSeeHtml( 'data-clear-filters' )
        ->assertSet( 'channel', '' );
} );

it( 'shows the no-templates empty state in a card when no rows exist', function (): void {
    Livewire::test( Index::class );
    NotificationTemplate::query()->delete();

    Livewire::test( Index::class )
        ->assertSee( 'No notification templates' )
        ->assertDontSeeHtml( 'data-notification-filters' );
} );
