<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\NotificationTemplate;
use Tests\Browser\Support\Locators;

it( 'edits a notification template and sees the preview change', function (): void {
    $template = NotificationTemplate::query()->where( 'channel', 'mail' )->whereNotNull( 'subject' )->firstOrFail();

    visit( route( 'artisanpack.ecommerce.admin.notifications.edit', [ 'template' => $template->id ] ) )
        ->assertDontSeeIn( '[data-preview-subject]', 'Browser preview check' )
        ->clear( Locators::field( 'notification-subject' ) )
        ->type( Locators::field( 'notification-subject' ), 'Browser preview check' )
        ->assertSeeIn( '[data-preview-subject]', 'Browser preview check' )
        ->assertNoJavaScriptErrors();
} );
