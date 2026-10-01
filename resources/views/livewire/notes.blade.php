{{--
    Internal notes. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notes.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
@endphp
<div>
    <x-artisanpack-card :title="__( 'Notes' )" shadow data-notes>
        @if ( $canAdd )
            <form wire:submit="addNote" class="mb-4 flex flex-col gap-2" data-note-form>
                <x-artisanpack-textarea
                    id="note-body-{{ $this->getId() }}"
                    :label="__( 'Add a note' )"
                    :hint="$isOrder ? __( 'Notes are internal unless you make them visible to the customer.' ) : __( 'Customer notes are internal and never shown to the customer.' )"
                    rows="3"
                    maxlength="{{ \ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notes::MAX_LENGTH }}"
                    wire:model="body"
                />

                @if ( $isOrder )
                    <x-artisanpack-toggle
                        id="note-visible-{{ $this->getId() }}"
                        :label="__( 'Visible to customer' )"
                        :hint="__( 'Shown in the customer\'s order history.' )"
                        wire:model="isCustomerVisible"
                    />
                @endif

                <x-artisanpack-button type="submit" size="sm" class="self-start" wire:loading.attr="disabled" :label="__( 'Add note' )" />
            </form>
        @endif

        @if ( [] === $notes )
            <x-artisanpack-ec-empty-state icon="o-chat-bubble-left-ellipsis" :title="__( 'No notes yet' )" :description="__( 'Notes from your team appear here.' )" />
        @else
            <ul class="flex list-none flex-col gap-3" aria-label="{{ __( 'Notes, newest first' ) }}">
                @foreach ( $notes as $note )
                    <li
                        wire:key="note-{{ $note['id'] }}"
                        @class( [
                            'rounded-box border p-3',
                            'border-info bg-info/10' => $note['visible'],
                            'border-base-300' => ! $note['visible'],
                        ] )
                        data-note="{{ $note['id'] }}"
                        data-customer-visible="{{ $note['visible'] ? 'true' : 'false' }}"
                    >
                        <div class="mb-1 flex flex-wrap items-center gap-2 text-sm">
                            @if ( $note['visible'] )
                                <x-artisanpack-badge :value="__( 'Visible to customer' )" icon="o-eye" class="badge-sm" color="info" />
                            @elseif ( $isOrder )
                                <x-artisanpack-badge :value="__( 'Internal' )" icon="o-lock-closed" class="badge-sm" color="neutral" />
                            @endif
                            <span class="font-semibold">{{ $note['author'] }}</span>
                            @if ( null !== $note['time'] )
                                <span class="opacity-75">&middot;</span>
                                <time class="opacity-75" datetime="{{ $note['time']->toIso8601String() }}" title="{{ LocalizedDate::format( $note['time'] ) }}">
                                    {{ $note['time']->diffForHumans() }}
                                    <span class="sr-only">({{ LocalizedDate::format( $note['time'] ) }})</span>
                                </time>
                            @endif
                            @if ( $note['canDelete'] )
                                <x-artisanpack-button
                                    variant="ghost"
                                    size="xs"
                                    icon="o-trash"
                                    class="ms-auto"
                                    wire:click="deleteNote( {{ $note['id'] }} )"
                                    wire:confirm="{{ __( 'Delete this note?' ) }}"
                                    wire:loading.attr="disabled"
                                    :label="__( 'Delete' )"
                                    :aria-label="__( 'Delete note by :author', [ 'author' => $note['author'] ] )"
                                />
                            @endif
                        </div>
                        <p class="whitespace-pre-line break-words">{{ $note['body'] }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-artisanpack-card>
</div>
