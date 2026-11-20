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
        ->assertSeeHtml( 'data-add-automation-unavailable' )
        ->assertSeeHtml( 'aria-describedby="board-automations-needs-columns"' )
        ->assertSeeHtml( 'id="board-automations-needs-columns"' )
        ->assertDontSeeHtml( 'wire:click="create"' );
} );

it( 'is denied without kanbanBoard.view', function (): void {
    Gate::define( 'ecommerce.kanbanBoard.view', static fn (): bool => false );

    Livewire::test( Automations::class, [ 'board' => $this->board ] )->assertForbidden();
} );

it( 'refuses writes without kanbanBoard.update', function (): void {
    Gate::define( 'ecommerce.kanbanBoard.update', static fn (): bool => false );

    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->assertDontSee( 'Add automation' )
        ->assertDontSeeHtml( 'data-automation-switch' )
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
        ->assertSet( 'confirmingDelete', true )
        ->assertSeeHtml( 'role="alertdialog"' )
        ->assertSeeHtml( 'data-autofocus' );

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

it( 'reads each automation as from → into on the design-system card, with a switch', function (): void {
    $automation = KanbanAutomation::factory()->create( [
        'board_id'       => $this->board->id,
        'from_column_id' => $this->packing->id,
        'to_column_id'   => $this->shipped->id,
        'trigger_key'    => 'send-email',
        'trigger_config' => [ 'to' => [ 'customer' ], 'subject' => 'Shipped', 'body' => '' ],
        'is_active'      => true,
    ] );

    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->assertSeeHtml( 'class="apui-card" aria-labelledby="board-automations-heading"' )
        ->assertSeeInOrder( [ 'Packing', 'to', 'Shipped', 'Send an email' ] )
        ->assertSeeHtml( 'role="switch"' )
        ->assertSeeHtml( 'aria-label="Packing to Shipped: Send an email"' )
        ->assertSeeHtml( 'wire:change="toggleActive( ' . $automation->id . ' )"' )
        ->assertDontSeeHtml( 'ec-settings-row--off' )
        ->assertDontSeeHtml( 'class="flex' );
} );

it( 'dims a switched-off automation and keeps its switch usable', function (): void {
    $automation = KanbanAutomation::factory()->create( [
        'board_id'     => $this->board->id,
        'to_column_id' => $this->shipped->id,
        'is_active'    => false,
    ] );

    $component = Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->assertSeeHtml( 'ec-settings-row--off' )
        ->assertSeeHtml( 'data-inactive-automation' )
        ->call( 'toggleActive', $automation->id )
        ->assertDontSeeHtml( 'ec-settings-row--off' );

    expect( $automation->fresh()->is_active )->toBeTrue()
        ->and( sentToasts( $component ) )->toContain( 'Automation switched on.' );
} );

it( 'shows the empty state when the board has columns but no automations', function (): void {
    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->assertSee( 'No automations yet' )
        ->assertSeeHtml( 'ec-empty-sunken' )
        ->assertSeeHtml( 'wire:click="create"' );
} );

it( 'warns about nested conditions in an alert with a one-click replace', function (): void {
    $automation = KanbanAutomation::factory()->create( [
        'board_id'     => $this->board->id,
        'to_column_id' => $this->shipped->id,
        'conditions'   => [ 'not' => [ 'type' => 'customer-first-order', 'config' => [] ] ],
    ] );

    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'edit', $automation->id )
        ->assertSeeHtml( 'class="apui-alert"' )
        ->assertSee( 'These conditions use &quot;any&quot; or &quot;not&quot; groups, which this screen cannot edit.', false )
        ->assertSee( 'They are kept as they are when you save.' )
        ->call( 'replaceComplexConditions' )
        ->assertDontSeeHtml( 'data-complex-conditions' );
} );

it( 'removes the signing secret with a button that can be undone before saving', function (): void {
    $automation = KanbanAutomation::factory()->create( [
        'board_id'       => $this->board->id,
        'to_column_id'   => $this->shipped->id,
        'trigger_key'    => 'webhook',
        'trigger_config' => [ 'url' => 'https://example.test/hook', 'secret' => 's3cret' ],
    ] );

    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'edit', $automation->id )
        ->assertSeeHtml( 'data-clear-secret' )
        ->set( 'clearSecret', true )
        ->assertSee( 'The signing secret is removed when you save.' )
        ->assertSeeHtml( 'data-keep-secret' )
        ->set( 'clearSecret', false )
        ->assertSeeHtml( 'data-clear-secret' )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $automation->fresh()->trigger_config['secret'] )->toBe( 's3cret' );
} );

it( 'opens the add drawer from an ?automation=new link', function (): void {
    Livewire::withQueryParams( [ 'automation' => 'new' ] )
        ->test( Automations::class, [ 'board' => $this->board ] )
        ->assertSet( 'editing', true )
        ->assertSet( 'automationId', null )
        ->assertSet( 'form.to_column_id', '' );
} );

it( 'opens an automation\'s edit drawer from an ?automation={id} link', function (): void {
    $automation = KanbanAutomation::factory()->create( [
        'board_id'       => $this->board->id,
        'from_column_id' => $this->packing->id,
        'to_column_id'   => $this->shipped->id,
        'trigger_key'    => 'webhook',
        'trigger_config' => [ 'url' => 'https://example.test/hook', 'secret' => 's3cret' ],
    ] );

    Livewire::withQueryParams( [ 'automation' => (string) $automation->id ] )
        ->test( Automations::class, [ 'board' => $this->board ] )
        ->assertSet( 'editing', true )
        ->assertSet( 'automationId', $automation->id )
        ->assertSet( 'form.to_column_id', $this->shipped->id )
        ->assertSet( 'secretIsSet', true )
        ->assertDontSeeHtml( 's3cret' );
} );

it( 'ignores ?automation= links it cannot act on', function ( Closure $value ): void {
    Livewire::withQueryParams( [ 'automation' => $value() ] )
        ->test( Automations::class, [ 'board' => $this->board ] )
        ->assertOk()
        ->assertSet( 'editing', false )
        ->assertSet( 'automationId', null );
} )->with( [
    'another board\'s automation' => [ static fn (): string => (string) KanbanAutomation::factory()->create()->id ],
    'a missing id'                => [ static fn (): string => '999999' ],
    'an empty value'              => [ static fn (): string => '' ],
    'a zero id'                   => [ static fn (): string => '0' ],
    'a signed id'                 => [ static fn (): string => '-1' ],
    'a word'                      => [ static fn (): string => 'edit' ],
    'an oversized id'             => [ static fn (): string => str_repeat( '9', 40 ) ],
    'an array'                    => [ static fn (): array => [ 'new' ] ],
] );

it( 'opens no drawer from ?automation= links without kanbanBoard.update', function ( string $which ): void {
    Gate::define( 'ecommerce.kanbanBoard.update', static fn (): bool => false );

    $automation = KanbanAutomation::factory()->create( [ 'board_id' => $this->board->id, 'to_column_id' => $this->shipped->id ] );

    Livewire::withQueryParams( [ 'automation' => 'new' === $which ? 'new' : (string) $automation->id ] )
        ->test( Automations::class, [ 'board' => $this->board ] )
        ->assertOk()
        ->assertSet( 'editing', false )
        ->assertSet( 'automationId', null );
} )->with( [ 'new', 'existing' ] );

it( 'opens no add drawer from ?automation=new on a board without columns', function (): void {
    Livewire::withQueryParams( [ 'automation' => 'new' ] )
        ->test( Automations::class, [ 'board' => KanbanBoard::factory()->create() ] )
        ->assertOk()
        ->assertSet( 'editing', false )
        ->assertSee( 'Add columns first' );
} );

it( 'opens the drawer from a link on the board edit page', function (): void {
    $automation = KanbanAutomation::factory()->create( [ 'board_id' => $this->board->id, 'to_column_id' => $this->shipped->id ] );

    $edit = route( 'artisanpack.ecommerce.admin.kanban-boards.edit', [ 'board' => $this->board->id ] );

    // The automation drawer is the page's only large one.
    $openDrawer = '/data-part="content"[^>]*data-state="open"[^>]*data-size="lg".{0,2000}?%s/s';

    expect( $this->get( $edit )->assertOk()->getContent() )->not->toMatch( '/data-part="content"[^>]*data-state="open"[^>]*data-size="lg"/' );

    expect( $this->get( $edit . '?automation=' . $automation->id )->assertOk()->getContent() )
        ->toMatch( sprintf( $openDrawer, 'Edit automation' ) );

    expect( $this->get( $edit . '?automation=new' )->assertOk()->getContent() )
        ->toMatch( sprintf( $openDrawer, 'New automation' ) );
} );

it( 'renders the webhook signing secret as a password field (L-S12)', function (): void {
    $html = Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'create' )
        ->set( 'form.trigger_key', 'webhook' )
        ->assertSeeHtml( 'data-config-secret' )
        ->html();

    expect( preg_match( '/<input[^>]*wire:model="form\.trigger_config\.secret"[^>]*>/', $html, $match ) )->toBe( 1 )
        ->and( $match[0] )->toContain( 'type="password"' )
        ->and( $match[0] )->toContain( 'autocomplete="new-password"' );
} );

it( 'refuses tampered trigger settings instead of failing (L-C1)', function (): void {
    app( ArtisanPackUI\Ecommerce\Registries\KanbanAutomationRegistry::class )->register( 'json-trigger', new class implements ArtisanPackUI\Ecommerce\Contracts\KanbanAutomationTrigger {
        public function key(): string
        {
            return 'json-trigger';
        }

        public function label(): string
        {
            return 'JSON trigger';
        }

        public function fire( ArtisanPackUI\Ecommerce\Models\Order $order, KanbanAutomation $automation, array $config ): void
        {
        }
    } );

    Livewire::test( Automations::class, [ 'board' => $this->board ] )
        ->call( 'create' )
        ->set( 'form.to_column_id', $this->shipped->id )
        ->set( 'form.trigger_key', 'json-trigger' )
        ->set( 'form.trigger_config', [ 'nested' => [ 'x' ] ] )
        ->call( 'save' )
        ->assertHasErrors( 'form.trigger_config' );

    expect( KanbanAutomation::query()->count() )->toBe( 0 );
} );
