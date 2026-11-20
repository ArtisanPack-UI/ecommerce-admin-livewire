{{--
    Kanban boards (#139). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards\Index.

    Every board in one card as a numbered list, because orders are routed
    onto boards top to bottom. Each row leads with its position, then the
    name (a link to the editor) and its badges: Default is the one primary
    badge, Switched off a warning, routing rules a neutral filter badge.
    Under the name sit the description and a muted line with the key in
    mono, the column count and the live card count, so staff see which
    boards are busy.

    The actions are Open board (when the kanban satellite is installed),
    Make default (active boards that aren't the default only), move up and
    down, edit and delete. New boards are created in a short dialog; the
    delete alertdialog names the board that takes over as the default.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="ec-stack" data-kanban-boards-index>
    <x-artisanpack-page-header :title="__( 'Kanban boards' )" :description="__( 'Boards group orders into columns by sub-status. Orders go onto every active board whose routing rules they match, in this order.' )" :level="1">
        @if ( $canCreate )
            <x-slot:actions>
                <x-artisanpack-button size="sm" leading-icon="plus" wire:click="create" data-new-board>{{ __( 'Add board' ) }}</x-artisanpack-button>
            </x-slot:actions>
        @endif
    </x-artisanpack-page-header>

    @if ( $boards->isEmpty() )
        <section class="apui-card" aria-label="{{ __( 'Kanban boards' ) }}">
            <x-artisanpack-ec-admin-empty-state
                icon="columns-3"
                :title="__( 'No kanban boards yet' )"
                :description="__( 'Add a board to track orders through your own stages, like printing, packing, and shipping.' )"
            >
                @if ( $canCreate )
                    <x-artisanpack-button leading-icon="plus" wire:click="create" data-empty-new-board>{{ __( 'Add board' ) }}</x-artisanpack-button>
                @endif
            </x-artisanpack-ec-admin-empty-state>
        </section>
    @else
        <section class="apui-card" aria-label="{{ __( 'Kanban boards' ) }}">
            <ol class="ec-plain-list ec-board-list" aria-label="{{ __( 'Kanban boards, in routing order' ) }}">
                @foreach ( $boards as $board )
                    @php $cards = (int) ( $cardCounts[ $board->id ] ?? 0 ); @endphp
                    <li wire:key="board-{{ $board->id }}" class="ec-board-row" data-board="{{ $board->key }}">
                        <span class="ec-rule-index ec-board-row__index" aria-hidden="true">{{ $loop->iteration }}</span>

                        <div class="ec-board-row__main">
                            <span class="ec-board-row__title">
                                @if ( isset( $editUrls[ $board->id ] ) )
                                    <x-artisanpack-link :href="$editUrls[ $board->id ]" :navigate="false" standalone class="ec-board-row__name">{{ $board->name }}</x-artisanpack-link>
                                @else
                                    <span class="ec-board-row__name">{{ $board->name }}</span>
                                @endif
                                @if ( $board->is_default )
                                    <x-artisanpack-badge color="primary" size="sm" leading-icon="star" data-default-board>{{ __( 'Default' ) }}</x-artisanpack-badge>
                                @endif
                                @unless ( $board->is_active )
                                    <x-artisanpack-badge color="warning" size="sm" leading-icon="minus" data-inactive-board>{{ __( 'Switched off' ) }}</x-artisanpack-badge>
                                @endunless
                                @if ( $board->hasRoutingRules() )
                                    <x-artisanpack-badge color="neutral" size="sm" leading-icon="filter" data-routing-board>{{ __( 'Has routing rules' ) }}</x-artisanpack-badge>
                                @endif
                            </span>
                            @if ( filled( $board->description ) )
                                <span class="ec-body">{{ $board->description }}</span>
                            @endif
                            <span class="ec-muted ec-board-row__meta" data-board-counts>
                                <bdi dir="ltr" class="ec-mono">{{ $board->key }}</bdi>
                                <span>{{ trans_choice( ':count column|:count columns', $board->columns_count, [ 'count' => $board->columns_count ] ) }}</span>
                                <span>{{ trans_choice( ':count card|:count cards', $cards, [ 'count' => $cards ] ) }}</span>
                            </span>
                        </div>

                        <div class="ec-board-row__actions">
                            @if ( null !== ( $boardUrls[ $board->id ] ?? null ) )
                                <x-artisanpack-button variant="outline" color="neutral" size="xs" leading-icon="columns-3" :href="$boardUrls[ $board->id ]" :aria-label="__( 'Open board: :name', [ 'name' => $board->name ] )" data-open-board>{{ __( 'Open board' ) }}</x-artisanpack-button>
                            @endif
                            @if ( $canUpdate )
                                @if ( $board->is_active && ! $board->is_default )
                                    <x-artisanpack-button
                                        variant="ghost"
                                        color="neutral"
                                        size="xs"
                                        wire:click="makeDefault( {{ $board->id }} )"
                                        wire:loading.attr="disabled"
                                        :aria-label="__( 'Make default: :name', [ 'name' => $board->name ] )"
                                        data-make-default>{{ __( 'Make default' ) }}</x-artisanpack-button>
                                @endif
                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="arrow-up" wire:click="move( {{ $board->id }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="boards" data-reorder-key="board-{{ $board->id }}" data-reorder-item="{{ $board->name }}" :disabled="$loop->first" :aria-label="__( 'Move :name up', [ 'name' => $board->name ] )" />
                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="arrow-down" wire:click="move( {{ $board->id }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="boards" data-reorder-key="board-{{ $board->id }}" data-reorder-item="{{ $board->name }}" :disabled="$loop->last" :aria-label="__( 'Move :name down', [ 'name' => $board->name ] )" />
                            @endif
                            @if ( isset( $editUrls[ $board->id ] ) )
                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="pencil" :href="$editUrls[ $board->id ]" :aria-label="__( 'Edit :name', [ 'name' => $board->name ] )" />
                            @endif
                            @if ( $canDelete )
                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="confirmDelete( {{ $board->id }} )" :aria-label="__( 'Delete :name', [ 'name' => $board->name ] )" />
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        <p class="ec-muted ec-board-note">
            <x-artisanpack-icon name="info" aria-hidden="true" />
            <span>{{ __( 'The default board catches the orders no other board took, as long as it has no routing rules of its own.' ) }}</span>
        </p>
    @endif

    <x-artisanpack-dialog
        wire:model="creating"
        :title="__( 'New board' )"
        :close-label="__( 'Close' )"
        :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
        data-new-board-dialog
    >
        @if ( $creating )
            <form id="new-board-form" wire:submit="save" class="ec-form-stack ec-stack" data-board-form>
                @include( 'ecommerce-admin::partials.error-summary' )
                <x-artisanpack-input id="board-name" :label="__( 'Name' )" wire:model.live.blur="form.name" maxlength="255" required />
                <x-artisanpack-input
                    id="board-key"
                    class="ec-mono-input"
                    dir="ltr"
                    :label="__( 'Key' )"
                    :hint="__( 'A unique, permanent identifier, like production. Filled from the name until you change it.' )"
                    wire:model.live.blur="form.key"
                    maxlength="120"
                    autocomplete="off"
                    spellcheck="false"
                    required
                />
                <x-artisanpack-textarea id="board-description" :label="__( 'Description' )" rows="3" wire:model="form.description" />
                <x-artisanpack-toggle id="board-active" :label="__( 'Switched on' )" :description="__( 'Orders are only routed onto active boards.' )" wire:model="form.is_active" />
                <x-artisanpack-toggle id="board-default" :label="__( 'Default board' )" :description="__( 'The default board catches orders no other board took. Only one board can be the default.' )" wire:model="form.is_default" />
            </form>
        @endif

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" color="neutral" wire:click="$set( 'creating', false )">{{ __( 'Cancel' ) }}</x-artisanpack-button>
            <x-artisanpack-button type="submit" form="new-board-form" wire:loading.attr="disabled" data-create-board>{{ __( 'Create board' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-dialog>

    @if ( null !== $deleting )
        {{-- Built outside the tag: a nested double quote in a component attribute stops Blade compiling it. --}}
        @php( $deleteTitle = __( 'Delete ":name"?', [ 'name' => $deleting->name ] ) )
        <x-artisanpack-dialog
            wire:model="confirmingDelete"
            role="alertdialog"
            size="sm"
            color="error"
            icon="trash"
            :title="$deleteTitle"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
            data-delete-confirm
        >
            <div class="ec-stack-sm" data-delete-board="{{ $deleting->key }}">
                <p class="ec-body">{{ __( 'Its columns and automations are deleted. Orders stay as they are, but lose their card on this board. This cannot be undone.' ) }}</p>
                @if ( $deletingCards > 0 )
                    <x-artisanpack-alert color="warning" icon="triangle-alert" data-delete-cards :title="trans_choice( ':count order is on this board right now.|:count orders are on this board right now.', $deletingCards, [ 'count' => $deletingCards ] )" />
                @endif
                @if ( $deleteBlocked )
                    <x-artisanpack-alert color="warning" icon="triangle-alert" data-delete-blocked :title="__( 'This is the default board and every other board is switched off. Switch on another board first, so it can become the default.' )" />
                @elseif ( null !== $successor )
                    @php( $successorTitle = __( '":name" becomes the default board.', [ 'name' => $successor->name ] ) )
                    <x-artisanpack-alert color="info" icon="info" data-delete-successor :title="$successorTitle" />
                @endif
            </div>

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelDelete" data-autofocus>{{ __( 'Keep board' ) }}</x-artisanpack-button>
                <x-artisanpack-button
                    color="error"
                    wire:click="delete( {{ \Illuminate\Support\Js::from( $deleteToken ) }} )"
                    wire:loading.attr="disabled"
                    :disabled="$deleteBlocked"
                    data-delete-button>{{ __( 'Delete board' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
