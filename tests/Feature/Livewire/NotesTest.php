<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\ActivityLogEntry;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerNote;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderNote;
use ArtisanPackUI\Ecommerce\Models\OrderTimelineEntry;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notes;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Timeline;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Fixtures\User;

beforeEach( function (): void {
    config()->set( 'auth.providers.users.model', User::class );
    grantAbilities( [ 'order.view', 'order.update', 'customer.view', 'customer.update' ] );
    $this->user = makeUser( [ 'name' => 'Ada Lovelace' ] );
    $this->actingAs( $this->user );
    $this->order = Order::factory()->create();
} );

it( 'renders the notes of an order, newest first, with author and visibility', function (): void {
    OrderNote::query()->create( [ 'order_id' => $this->order->id, 'author_user_id' => $this->user->id, 'body' => 'Older note', 'is_customer_visible' => false, 'created_at' => now()->subHour() ] );
    OrderNote::query()->create( [ 'order_id' => $this->order->id, 'author_user_id' => null, 'body' => 'Shipped early', 'is_customer_visible' => true ] );

    Livewire::test( Notes::class, [ 'subject' => $this->order ] )
        ->assertOk()
        ->assertSeeInOrder( [ 'Visible to customer', 'System', 'Shipped early', 'Internal', 'Ada Lovelace', 'Older note' ] )
        ->assertSeeHtml( 'data-customer-visible="true"' )
        ->assertSeeHtml( 'data-customer-visible="false"' );
} );

it( 'is denied without view on the subject', function (): void {
    Gate::define( 'ecommerce.order.view', static fn (): bool => false );

    Livewire::test( Notes::class, [ 'subject' => $this->order ] )->assertForbidden();
} );

it( 'hides the form and refuses to add without update', function (): void {
    Gate::define( 'ecommerce.order.update', static fn (): bool => false );

    Livewire::test( Notes::class, [ 'subject' => $this->order ] )
        ->assertDontSeeHtml( 'data-note-form' )
        ->set( 'body', 'Sneaky' )
        ->call( 'addNote' )
        ->assertForbidden();

    expect( OrderNote::query()->count() )->toBe( 0 );
} );

it( 'adds an internal order note by default, records the author, and refreshes the timeline', function (): void {
    Livewire::test( Notes::class, [ 'subject' => $this->order ] )
        ->set( 'body', "Customer called,\nwants delivery after the 15th" )
        ->call( 'addNote' )
        ->assertHasNoErrors()
        ->assertSet( 'body', '' )
        ->assertSet( 'isCustomerVisible', false )
        ->assertDispatched( Notes::NOTES_CHANGED_EVENT, subjectType: Order::class, subjectId: $this->order->id )
        ->assertSee( 'wants delivery after the 15th' );

    $note = OrderNote::query()->sole();

    expect( $note->author_user_id )->toBe( $this->user->id )
        ->and( $note->is_customer_visible )->toBeFalse()
        ->and( OrderTimelineEntry::query()->where( 'order_id', $this->order->id )->where( 'event_type', 'note.added' )->exists() )->toBeTrue();

    Livewire::test( Timeline::class, [ 'subject' => $this->order ] )->assertSee( 'Note added' );
} );

it( 'adds a customer-visible order note when toggled', function (): void {
    Livewire::test( Notes::class, [ 'subject' => $this->order ] )
        ->set( 'body', 'Your parcel is on its way' )
        ->set( 'isCustomerVisible', true )
        ->call( 'addNote' )
        ->assertHasNoErrors();

    expect( OrderNote::query()->sole()->is_customer_visible )->toBeTrue();
} );

it( 'strips markup from the body and escapes it on output', function (): void {
    Livewire::test( Notes::class, [ 'subject' => $this->order ] )
        ->set( 'body', '<script>alert(1)</script>Hello <b>there</b>' )
        ->call( 'addNote' )
        ->assertHasNoErrors()
        ->assertDontSeeHtml( '<script>alert(1)</script>' );

    expect( OrderNote::query()->sole()->body )->toBe( 'alert(1)Hello there' );

    OrderNote::query()->create( [ 'order_id' => $this->order->id, 'body' => '<img src=x onerror=alert(1)>' ] );

    Livewire::test( Notes::class, [ 'subject' => $this->order ] )
        ->assertDontSeeHtml( '<img src=x' )
        ->assertSeeHtml( '&lt;img src=x onerror=alert(1)&gt;' );
} );

it( 'validates the body', function ( string $body ): void {
    Livewire::test( Notes::class, [ 'subject' => $this->order ] )
        ->set( 'body', $body )
        ->call( 'addNote' )
        ->assertHasErrors( 'body' );

    expect( OrderNote::query()->count() )->toBe( 0 );
} )->with( [
    'empty'          => [ '' ],
    'only markup'    => [ '<p></p>' ],
    'only spaces'    => [ '   ' ],
    'too long'       => [ str_repeat( 'a', Notes::MAX_LENGTH + 1 ) ],
] );

it( 'adds customer notes without a visibility toggle', function (): void {
    $customer = Customer::factory()->create();

    Livewire::test( Notes::class, [ 'subject' => $customer ] )
        ->assertDontSee( 'Visible to customer' )
        ->assertSee( 'Customer notes are internal' )
        ->set( 'body', 'Past dispute over a refund' )
        ->call( 'addNote' )
        ->assertHasNoErrors()
        ->assertDispatched( Notes::NOTES_CHANGED_EVENT );

    expect( CustomerNote::query()->sole()->body )->toBe( 'Past dispute over a refund' )
        ->and( ActivityLogEntry::query()->where( 'event_type', 'note.added' )->exists() )->toBeTrue();
} );

it( 'lets the author delete their own note', function (): void {
    $note = OrderNote::query()->create( [ 'order_id' => $this->order->id, 'author_user_id' => $this->user->id, 'body' => 'Mine' ] );

    Gate::define( 'ecommerce.order.update', static fn (): bool => false );

    Livewire::test( Notes::class, [ 'subject' => $this->order ] )
        ->call( 'deleteNote', $note->id )
        ->assertDispatched( Notes::NOTES_CHANGED_EVENT );

    expect( OrderNote::query()->count() )->toBe( 0 );
} );

it( 'refuses to delete someone else\'s note without update', function (): void {
    $other = makeUser();
    $note  = OrderNote::query()->create( [ 'order_id' => $this->order->id, 'author_user_id' => $other->id, 'body' => 'Theirs' ] );

    Gate::define( 'ecommerce.order.update', static fn (): bool => false );

    Livewire::test( Notes::class, [ 'subject' => $this->order ] )
        ->assertDontSeeHtml( 'deleteNote(' )
        ->call( 'deleteNote', $note->id )
        ->assertForbidden();

    expect( OrderNote::query()->count() )->toBe( 1 );
} );

it( 'lets a user with update delete anyone\'s note, but only on this subject', function (): void {
    $other     = Order::factory()->create();
    $note      = OrderNote::query()->create( [ 'order_id' => $this->order->id, 'author_user_id' => makeUser()->id, 'body' => 'Theirs' ] );
    $elsewhere = OrderNote::query()->create( [ 'order_id' => $other->id, 'body' => 'Other order' ] );

    Livewire::test( Notes::class, [ 'subject' => $this->order ] )
        ->call( 'deleteNote', $elsewhere->id )
        ->call( 'deleteNote', $note->id );

    expect( OrderNote::query()->pluck( 'id' )->all() )->toBe( [ $elsewhere->id ] );
} );

it( 'refuses unsupported subjects', function (): void {
    expect( fn () => Livewire::test( Notes::class, [ 'subject' => makeUser() ] ) )->toThrow( 'Notes cannot be attached to a ' . User::class );
} );

it( 'shows notes on the order page', function (): void {
    grantAbilities( [ 'order.viewAny' ] );

    $this->get( route( 'artisanpack.ecommerce.admin.orders.show', [ 'order' => $this->order->id ] ) )
        ->assertOk()
        ->assertSeeLivewire( Notes::class );
} );
