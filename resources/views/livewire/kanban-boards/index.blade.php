{{--
    Kanban boards. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-6">
    <x-artisanpack-page-header :title="__( 'Kanban boards' )" :description="__( 'Boards group orders into columns by sub-status. Orders go onto every active board whose routing rules they match, in this order.' )" :level="1">
        @if ( $canCreate )
            <x-slot:actions>
                <x-artisanpack-button color="primary" leading-icon="o-plus" wire:click="create">{{ __( 'Add board' ) }}</x-artisanpack-button>
            </x-slot:actions>
        @endif
    </x-artisanpack-page-header>

    @if ( $boards->isEmpty() )
        <x-artisanpack-ec-admin-empty-state
            icon="o-view-columns"
            :title="__( 'No kanban boards yet' )"
            :description="__( 'Add a board to track orders through your own stages, like printing, packing, and shipping.' )"
        />
    @else
        <ul class="flex list-none flex-col divide-y divide-base-300 rounded-box border border-base-300" aria-label="{{ __( 'Kanban boards' ) }}">
            @foreach ( $boards as $board )
                @php $cards = (int) ( $cardCounts[ $board->id ] ?? 0 ); @endphp
                <li wire:key="board-{{ $board->id }}" class="flex flex-wrap items-center gap-3 p-3" data-board="{{ $board->key }}">
                    <div class="min-w-0 grow">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ( isset( $editUrls[ $board->id ] ) )
                                <a href="{{ $editUrls[ $board->id ] }}" class="link font-semibold">{{ $board->name }}</a>
                            @else
                                <span class="font-semibold">{{ $board->name }}</span>
                            @endif
                            <code class="text-sm opacity-75">{{ $board->key }}</code>
                            @if ( $board->is_default )
                                <x-artisanpack-badge :value="__( 'Default' )" color="primary" size="sm" data-default-board />
                            @endif
                            @unless ( $board->is_active )
                                <x-artisanpack-badge :value="__( 'Switched off' )" size="sm" variant="ghost" data-inactive-board />
                            @endunless
                            @if ( $board->hasRoutingRules() )
                                <x-artisanpack-badge :value="__( 'Has routing rules' )" size="sm" variant="outline" />
                            @endif
                        </div>
                        @if ( filled( $board->description ) )
                            <p class="text-sm opacity-75">{{ $board->description }}</p>
                        @endif
                    </div>

                    <span class="text-sm opacity-75" data-board-counts>
                        {{ trans_choice( ':count column|:count columns', $board->columns_count, [ 'count' => $board->columns_count ] ) }}
                        &middot;
                        {{ trans_choice( ':count automation|:count automations', $board->automations_count, [ 'count' => $board->automations_count ] ) }}
                        &middot;
                        {{ trans_choice( ':count card|:count cards', $cards, [ 'count' => $cards ] ) }}
                    </span>

                    <div class="flex flex-wrap items-center gap-1">
                        @if ( null !== ( $boardUrls[ $board->id ] ?? null ) )
                            <x-artisanpack-button variant="ghost" size="sm" leading-icon="o-arrow-top-right-on-square" :href="$boardUrls[ $board->id ]" data-open-board>{{ __( 'Open board' ) }}</x-artisanpack-button>
                        @endif
                        @if ( $canUpdate )
                            <x-artisanpack-button
                                variant="ghost"
                                size="sm"
                                leading-icon="o-arrow-up"
                                wire:click="move( {{ $board->id }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="boards" data-reorder-key="board-{{ $board->id }}" data-reorder-item="{{ $board->name }}"
                                :disabled="$loop->first"
                                :aria-label="__( 'Move :name up', [ 'name' => $board->name ] )"
                            />
                            <x-artisanpack-button
                                variant="ghost"
                                size="sm"
                                leading-icon="o-arrow-down"
                                wire:click="move( {{ $board->id }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="boards" data-reorder-key="board-{{ $board->id }}" data-reorder-item="{{ $board->name }}"
                                :disabled="$loop->last"
                                :aria-label="__( 'Move :name down', [ 'name' => $board->name ] )"
                            />
                            @unless ( $board->is_default )
                                <x-artisanpack-button
                                    variant="ghost"
                                    size="sm"
                                    leading-icon="o-star"
                                    wire:click="makeDefault( {{ $board->id }} )"
                                    :disabled="! $board->is_active"
                                    :aria-label="__( 'Make default: :name', [ 'name' => $board->name ] )">{{ __( 'Make default' ) }}</x-artisanpack-button>
                            @endunless
                        @endif
                        @if ( isset( $editUrls[ $board->id ] ) )
                            <x-artisanpack-button variant="ghost" size="sm" leading-icon="o-pencil" :href="$editUrls[ $board->id ]" :aria-label="__( 'Edit :name', [ 'name' => $board->name ] )" />
                        @endif
                        @if ( $canDelete )
                            <x-artisanpack-button variant="ghost" size="sm" leading-icon="o-trash" wire:click="confirmDelete( {{ $board->id }} )" :aria-label="__( 'Delete :name', [ 'name' => $board->name ] )" />
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <p class="text-sm opacity-75">
            {{ __( 'The default board catches the orders no other board took, as long as it has no routing rules of its own.' ) }}
        </p>
    @endif

    <x-artisanpack-drawer
        wire:model="creating"
        :title="__( 'New board' )"
        side="end"
        class="w-full max-w-lg"
    >
        <form wire:submit="save" class="flex flex-col gap-4" data-board-form>
            @include( 'ecommerce-admin::partials.error-summary' )
            <x-artisanpack-input id="board-name" :label="__( 'Name' )" wire:model.live.blur="form.name" maxlength="255" required />
            <x-artisanpack-input id="board-key" :label="__( 'Key' )" :hint="__( 'A unique, permanent identifier, like production. Filled from the name until you change it.' )" wire:model.live.blur="form.key" maxlength="120" required />
            <x-artisanpack-textarea id="board-description" :label="__( 'Description' )" rows="2" wire:model="form.description" />
            <x-artisanpack-toggle id="board-active" :label="__( 'Switched on' )" :description="__( 'Orders are only routed onto active boards.' )" wire:model="form.is_active" />
            <x-artisanpack-toggle id="board-default" :label="__( 'Default board' )" :description="__( 'The default board catches orders no other board took. Only one board can be the default.' )" wire:model="form.is_default" />

            <div class="flex justify-end gap-2">
                <x-artisanpack-button variant="ghost" wire:click="$set( 'creating', false )">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled">{{ __( 'Create board' ) }}</x-artisanpack-button>
            </div>
        </form>
    </x-artisanpack-drawer>

    @if ( null !== $deleting )
        @php $deleteTitle = __( 'Delete ":name"?', [ 'name' => $deleting->name ] ); @endphp
        <x-artisanpack-dialog wire:model="confirmingDelete" :title="$deleteTitle">
            <div class="flex flex-col gap-3" data-delete-board="{{ $deleting->key }}">
                <p>{{ __( 'Its columns and automations are deleted. Orders stay as they are, but lose their card on this board. This cannot be undone.' ) }}</p>
                @if ( $deletingCards > 0 )
                    <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" role="status" data-delete-cards>
                        {{ trans_choice( ':count order is on this board right now.|:count orders are on this board right now.', $deletingCards, [ 'count' => $deletingCards ] ) }}
                    </x-artisanpack-alert>
                @endif
                @if ( $deleteBlocked )
                    <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" role="status" data-delete-blocked>
                        {{ __( 'This is the default board and every other board is switched off. Switch on another board first, so it can become the default.' ) }}
                    </x-artisanpack-alert>
                @elseif ( null !== $successor )
                    <p data-delete-successor>{{ __( '":name" becomes the default board.', [ 'name' => $successor->name ] ) }}</p>
                @endif
            </div>

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" wire:click="cancelDelete">{{ __( 'Keep board' ) }}</x-artisanpack-button>
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
