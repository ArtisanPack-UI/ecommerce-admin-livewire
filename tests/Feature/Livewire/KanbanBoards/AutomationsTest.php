<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanAutomation;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\Ecommerce\Webhooks\WebhookUrlGuard;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards\Automations;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    // Hosts resolve to a public address unless they are IP literals.
    WebhookUrlGuard::resolveUsing( static fn ( string $host ): array => [ '93.184.216.34' ] );
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanBoard.update', 'product.viewAny' ] );
    $this->actingAs( makeUser() );

    $this->board   = KanbanBoard::factory()->create();
    $this->packing = KanbanColumn::factory()->create( [
        'board_id'       => $this->board->id,
        'substatus_id'   => OrderSubstatus::query()->where( 'key', 'in-progress' )->value( 'id' ),
        'label_override' => 'Packing',
        'position'       => 0,
    ] );
    $this->shipped = KanbanColumn::factory()->create( [
        'board_id'       => $this->board->id,
        'substatus_id'   => OrderSubstatus::query()->where( 'key', 'completed' )->value( 'id' ),
        'label_override' => 'Shipped',
        'position'       => 1,
    ] );
} );

it( 'lists the automations with their trigger and conditions', function (): void {
    KanbanAutomation::factory()->create( [
        'board_id'       => $this->board->id,
        'from_column_id' => null,
        'to_column_id'   => $this->shipped->id,
        'trigger_key'    => 'send-email',
        'trigger_config' => [ 'to' => [ 'customer' ], 'subject' => 'Shipped', 'body' => '' ],
        'conditions'     => [ [ 'type' => 'customer-first-order', 'config' => [] ] ],
        'is_active'      => false,
    ] );

    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->assertOk()
        ->assertSee( [ 'Any column', 'Shipped', 'Send an email' ] )
        ->assertSee( 'Orders match when it is the customer&#039;s first order.', false )
        ->assertSeeHtml( 'data-inactive-automation' );
} );

it( 'asks for columns first on a board without any', function (): void {
    Livewire::test( Automations::class, [ 'board' => KanbanBoard::factory()->create() ] )
        ->assertSee( 'Add columns first' )
        ->assertDontSee( 'Add automation' );
} );

it( 'is denied without kanbanBoard.view', function (): void {
    Gate::define( 'ecommerce.kanbanBoard.view', static fn (): bool => false );

    Livewire::test( Automations::class, [ 'board' => $this->board ] )->assertForbidden();
} );

it( 'refuses writes without kanbanBoard.update', function (): void {
    Gate::define( 'ecommerce.kanbanBoard.update', static fn (): bool => false );

    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->assertDontSee( 'Add automation' )
        ->call( 'create' )
        ->assertForbidden();
} );

it( 'emails the customer when a card reaches Shipped, for first orders only', function (): void {
    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'create' )
        ->set( 'form.to_column_id', $this->shipped->id )
        ->set( 'form.trigger_key', 'send-email' )
        ->assertSeeHtml( 'data-automation-config' )
        ->set( 'form.trigger_config.to', [ 'customer' ] )
        ->set( 'form.trigger_config.subject', 'Your order shipped' )
        ->set( 'ruleToAdd.conditions', 'customer-first-order' )->call( 'addRule', 'conditions' )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'editing', false );

    $automation = KanbanAutomation::query()->where( 'board_id', $this->board->id )->sole();

    expect( $automation )
        ->from_column_id->toBeNull()
        ->to_column_id->toBe( $this->shipped->id )
        ->trigger_key->toBe( 'send-email' )
        ->is_active->toBeTrue()
        ->conditions->toBe( [ [ 'type' => 'customer-first-order', 'config' => [] ] ] )
        ->and( $automation->trigger_config['to'] )->toBe( [ 'customer' ] )
        ->and( $automation->trigger_config['subject'] )->toBe( 'Your order shipped' );
} );

it( 'saves a dispatch-job trigger when jobs are configured', function (): void {
    config()->set( 'artisanpack.ecommerce.kanban.dispatchable_jobs', [ Illuminate\Queue\CallQueuedClosure::class ] );

    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'create' )
        ->set( 'form.to_column_id', $this->shipped->id )
        ->set( 'form.trigger_key', 'dispatch-job' )
        ->set( 'form.trigger_config.job', Illuminate\Queue\CallQueuedClosure::class )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( KanbanAutomation::query()->sole()->trigger_config )->toBe( [ 'job' => Illuminate\Queue\CallQueuedClosure::class ] );
} );

it( 'edits an automation and keeps a stored signing secret', function (): void {
    $automation = KanbanAutomation::factory()->create( [
        'board_id'       => $this->board->id,
        'from_column_id' => $this->packing->id,
        'to_column_id'   => $this->shipped->id,
        'trigger_key'    => 'webhook',
        'trigger_config' => [ 'url' => 'https://example.test/hook', 'secret' => 's3cret' ],
    ] );

    $component = Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'edit', $automation->id )
        ->assertSet( 'secretIsSet', true )
        ->assertSet( 'form.trigger_config.secret', '' )
        ->assertSet( 'form.from_column_id', $this->packing->id )
        ->assertSeeHtml( 'data-secret-set' )
        ->assertDontSeeHtml( 's3cret' )
        ->set( 'form.trigger_config.url', 'https://example.test/new' )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $automation->fresh()->trigger_config )->toBe( [ 'url' => 'https://example.test/new', 'secret' => 's3cret' ] );

    $component->call( 'edit', $automation->id )
        ->set( 'form.trigger_config.secret', 'rotated' )
        ->call( 'save' );

    expect( $automation->fresh()->trigger_config['secret'] )->toBe( 'rotated' );

    $component->call( 'edit', $automation->id )
        ->set( 'clearSecret', true )
        ->call( 'save' );

    expect( $automation->fresh()->trigger_config )->not->toHaveKey( 'secret' );
} );

it( 'resets the settings when the trigger changes', function (): void {
    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'create' )
        ->set( 'form.trigger_key', 'webhook' )
        ->set( 'form.trigger_config.url', 'https://example.test' )
        ->set( 'form.trigger_key', 'update-order-field' )
        ->assertSet( 'form.trigger_config.url', null )
        ->assertSet( 'form.trigger_config.field', '' );
} );

it( 'validates the automation', function (): void {
    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'create' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.to_column_id' => 'required', 'form.trigger_key' => 'required' ] )
        ->set( 'form.from_column_id', $this->shipped->id )
        ->set( 'form.to_column_id', $this->shipped->id )
        ->set( 'form.trigger_key', 'webhook' )
        ->set( 'form.trigger_config.url', 'https://example.test' )
        ->call( 'save' )
        ->assertHasErrors( 'form.from_column_id' )
        ->set( 'form.from_column_id', $this->packing->id )
        ->set( 'form.trigger_config.url', 'not a url' )
        ->call( 'save' )
        ->assertHasErrors( 'form.trigger_config.url' );

    expect( KanbanAutomation::query()->count() )->toBe( 0 );
} );

it( 'refuses a column from another board', function (): void {
    $elsewhere = KanbanColumn::factory()->create( [ 'substatus_id' => OrderSubstatus::query()->where( 'key', 'completed' )->value( 'id' ) ] );

    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'create' )
        ->set( 'form.to_column_id', $elsewhere->id )
        ->set( 'form.trigger_key', 'webhook' )
        ->set( 'form.trigger_config.url', 'https://example.test' )
        ->call( 'save' )
        ->assertHasErrors( [ 'form.to_column_id' => 'exists' ] );
} );

it( 'keeps nested conditions until they are replaced', function (): void {
    $tree       = [ 'not' => [ 'type' => 'customer-first-order', 'config' => [] ] ];
    $automation = KanbanAutomation::factory()->create( [
        'board_id'       => $this->board->id,
        'to_column_id'   => $this->shipped->id,
        'trigger_config' => [ 'field' => 'meta.touched', 'value' => 'yes' ],
        'conditions'     => $tree,
    ] );

    $component = Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->assertSee( 'Orders match nested conditions.' )
        ->call( 'edit', $automation->id )
        ->assertSeeHtml( 'data-complex-conditions' )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $automation->fresh()->conditions )->toBe( $tree );

    $component->call( 'edit', $automation->id )->call( 'replaceComplexConditions' )->call( 'save' );

    expect( $automation->fresh()->conditions )->toBe( [] );
} );

it( 'switches an automation off and on', function (): void {
    $automation = KanbanAutomation::factory()->create( [ 'board_id' => $this->board->id, 'to_column_id' => $this->shipped->id ] );

    $component = Livewire::test( Automations::class, [ 'board' => $this->board ] )->call( 'toggleActive', $automation->id );

    expect( $automation->fresh()->is_active )->toBeFalse();

    $component->call( 'toggleActive', $automation->id );

    expect( $automation->fresh()->is_active )->toBeTrue();
} );

it( 'deletes an automation after confirmation', function (): void {
    $automation = KanbanAutomation::factory()->create( [ 'board_id' => $this->board->id, 'to_column_id' => $this->shipped->id ] );

    $component = Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'confirmDelete', $automation->id )
        ->assertSet( 'confirmingDelete', true );

    $component->call( 'delete', $component->viewData( 'deleteToken' ) )->assertSet( 'confirmingDelete', false );

    expect( KanbanAutomation::query()->whereKey( $automation->id )->exists() )->toBeFalse();
} );

it( 'ignores automations of other boards', function (): void {
    $other = KanbanAutomation::factory()->create();

    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'edit', $other->id )
        ->assertSet( 'editing', false )
        ->call( 'toggleActive', $other->id )
        ->call( 'confirmDelete', $other->id )
        ->assertSet( 'confirmingDelete', false );

    expect( $other->fresh()->is_active )->toBeTrue();
} );

it( 'refreshes its column choices when the columns change', function (): void {
    $component = Livewire::test( Automations::class, [ 'board' => $this->board ] );

    KanbanColumn::factory()->create( [
        'board_id'       => $this->board->id,
        'substatus_id'   => OrderSubstatus::query()->where( 'key', 'cancelled' )->value( 'id' ),
        'label_override' => 'Returned',
    ] );

    $component->dispatch( 'kanban-columns-changed' );

    expect( collect( $component->viewData( 'columnOptions' ) )->pluck( 'name' )->all() )->toContain( 'Returned' );
} );

it( 'clears a typed secret from the component after saving', function (): void {
    $automation = KanbanAutomation::factory()->create( [
        'board_id'       => $this->board->id,
        'from_column_id' => $this->packing->id,
        'to_column_id'   => $this->shipped->id,
        'trigger_key'    => 'webhook',
        'trigger_config' => [ 'url' => 'https://example.test/hook' ],
    ] );

    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'edit', $automation->id )
        ->set( 'form.trigger_config.secret', 'typed-secret' )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'form.trigger_config.secret', '' )
        ->assertDontSeeHtml( 'typed-secret' );

    expect( $automation->fresh()->trigger_config['secret'] )->toBe( 'typed-secret' );
} );

it( 'still shows a stored secret after switching triggers and back', function (): void {
    $automation = KanbanAutomation::factory()->create( [
        'board_id'       => $this->board->id,
        'from_column_id' => $this->packing->id,
        'to_column_id'   => $this->shipped->id,
        'trigger_key'    => 'webhook',
        'trigger_config' => [ 'url' => 'https://example.test/hook', 'secret' => 's3cret' ],
    ] );

    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'edit', $automation->id )
        ->set( 'form.trigger_key', 'send-email' )
        ->assertSet( 'secretIsSet', false )
        ->set( 'form.trigger_key', 'webhook' )
        ->assertSet( 'secretIsSet', true );
} );

afterEach( function (): void {
    WebhookUrlGuard::resolveUsing( null );
} );

it( 'refuses a webhook trigger URL that points at a private address', function ( string $url ): void {
    config()->set( 'artisanpack.ecommerce.webhooks.allow_insecure_urls', true );

    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'create' )
        ->set( 'form.to_column_id', $this->shipped->id )
        ->set( 'form.trigger_key', 'webhook' )
        ->set( 'form.trigger_config.url', $url )
        ->call( 'save' )
        ->assertHasErrors( 'form.trigger_config.url' );

    expect( KanbanAutomation::query()->count() )->toBe( 0 );
} )->with( [ 'loopback' => 'http://127.0.0.1/', 'metadata' => 'http://169.254.169.254/latest' ] );

it( 'refuses a plain-http webhook trigger URL unless insecure URLs are allowed', function (): void {
    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'create' )
        ->set( 'form.to_column_id', $this->shipped->id )
        ->set( 'form.trigger_key', 'webhook' )
        ->set( 'form.trigger_config.url', 'http://hooks.example.test/' )
        ->call( 'save' )
        ->assertHasErrors( 'form.trigger_config.url' );
} );
