<?php

declare( strict_types=1 );

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * Renders the error summary partial with the given errors and data.
 *
 * @param  array<string, list<string>>  $messages
 * @param  array<string, mixed>         $data
 */
function renderErrorSummary( array $messages = [], array $data = [] ): string
{
    $errors = ( new ViewErrorBag() )->put( 'default', new MessageBag( $messages ) );

    return view( 'ecommerce-admin::partials.error-summary', [ 'errors' => $errors, ...$data ] )->render();
}

it( 'renders nothing without errors', function (): void {
    expect( trim( renderErrorSummary() ) )->toBe( '' );
} );

it( 'lists each field to fix as a link to it, under a pluralized title', function (): void {
    $html = renderErrorSummary( [
        'form.price'  => [ 'Enter a price.', 'The price must be a number.' ],
        'form.sku'    => [ 'Already used by another variant.' ],
        'form.weight' => [ 'Enter a number, like 0.45.' ],
    ] );

    expect( $html )->toContain( 'class="apui-validation-summary"' )
        ->toContain( 'role="alert"' )
        ->toContain( 'tabindex="-1"' )
        ->toContain( 'data-error-summary' )
        ->toContain( 'Not saved. Fix the 3 highlighted fields.' )
        ->toContain( 'href="#form.price"' )
        ->toContain( '>Enter a price.</a>' )
        ->not->toContain( 'The price must be a number.' )
        ->toContain( 'href="#form.sku"' )
        ->toContain( 'href="#form.weight"' );
} );

it( 'uses the singular title for one field', function (): void {
    expect( renderErrorSummary( [ 'email' => [ 'Enter an email address.' ] ] ) )
        ->toContain( 'Not saved. Fix the highlighted field.' );
} );

it( 'uses an error Alert with one action for a failure outside the form', function (): void {
    $html = renderErrorSummary( [], [
        'failure'            => 'Not saved. Someone else changed this product.',
        'failureDescription' => 'Reload to see their changes.',
        'actionLabel'        => 'Reload',
        'actionMethod'       => 'reloadProduct',
    ] );

    expect( $html )->toContain( 'class="apui-alert"' )
        ->toContain( 'data-color="error"' )
        ->toContain( 'role="alert"' )
        ->toContain( 'data-error-alert' )
        ->toContain( 'Not saved. Someone else changed this product.' )
        ->toContain( 'Reload to see their changes.' )
        ->toContain( 'wire:click="reloadProduct"' )
        ->not->toContain( 'apui-validation-summary' )
        ->not->toContain( '<x-' );
} );

it( 'ignores a $message left behind by the including view', function (): void {
    expect( trim( renderErrorSummary( [], [ 'message' => 'Leftover from @error' ] ) ) )->toBe( '' );
} );
