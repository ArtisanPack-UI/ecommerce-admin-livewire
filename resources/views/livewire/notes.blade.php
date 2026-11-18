{{--
    Internal notes (#103). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notes.

    The composer sits at the top: a textarea with a character count, the
    "Visible to customer" toggle on orders, and Add note. Notes list newest
    first, each with its author, time and a visibility badge with an icon,
    so internal and customer-visible notes differ by more than color.
    Delete is a ghost icon button that confirms in a small dialog.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notes;
    use ArtisanPack\LivewireUiComponents\View\Components\Avatar;
@endphp
<div>
    <x-artisanpack-card :title="__( 'Notes' )" :classes="[ 'body' => 'ec-stack-lg' ]" data-notes>
        @if ( $canAdd )
            <form
                wire:submit="addNote"
                class="ec-stack-sm"
                x-data="{ hasText: false }"
                x-on:input="if ( 'TEXTAREA' === $event.target.tagName ) hasText = '' !== $event.target.value.trim()"
                x-on:{{ Notes::NOTES_CHANGED_EVENT }}.window="hasText = false"
                data-note-form
            >
                <x-artisanpack-textarea
                    id="note-body-{{ $this->getId() }}"
                    :label="__( 'Add a note' )"
                    :hint="$isOrder ? __( 'Notes are internal unless you make them visible to the customer.' ) : __( 'Customer notes are internal and never shown to the customer.' )"
                    :rows="3"
                    :max-length="Notes::MAX_LENGTH"
                    wire:model="body"
                />

                <div class="ec-note-actions">
                    @if ( $isOrder )
                        <x-artisanpack-toggle
                            id="note-visible-{{ $this->getId() }}"
                            size="sm"
                            :label="__( 'Visible to customer' )"
                            wire:model="isCustomerVisible"
                        />
                    @endif

                    <x-artisanpack-button
                        type="submit"
                        size="sm"
                        class="ec-push-end"
                        x-bind:aria-disabled="hasText ? 'false' : 'true'"
                        wire:loading.attr="disabled"
                        wire:target="addNote"
                    >{{ __( 'Add note' ) }}</x-artisanpack-button>
                </div>
            </form>
        @endif

        @if ( [] === $notes )
            <x-artisanpack-ec-admin-empty-state
                size="sm"
                class="ec-empty-sunken"
                :heading-level="4"
                icon="message-square"
                :title="__( 'No notes yet' )"
                :description="__( 'Notes from your team appear here.' )"
            />
        @else
            <x-artisanpack-list variant="divided" ordered class="ec-notes" :aria-label="__( 'Notes, newest first' )">
                @foreach ( $notes as $note )
                    <li
                        wire:key="note-{{ $note['id'] }}"
                        class="ec-note"
                        data-note="{{ $note['id'] }}"
                        data-customer-visible="{{ $note['visible'] ? 'true' : 'false' }}"
                    >
                        <x-artisanpack-avatar size="sm" :initials="Avatar::initialsFor( $note['author'] )" :color="Avatar::colorFor( $note['author'] )" class="ec-note-avatar" />
                        <div class="ec-note-meta">
                            <span class="ec-note-author">{{ $note['author'] }}</span>
                            @if ( null !== $note['time'] )
                                <time class="ec-note-time" datetime="{{ $note['time']->toIso8601String() }}" title="{{ LocalizedDate::format( $note['time'] ) }}">
                                    {{ $note['time']->diffForHumans() }}
                                    <span class="apui-sr-only">({{ LocalizedDate::format( $note['time'] ) }})</span>
                                </time>
                            @endif
                            @if ( $note['visible'] )
                                <x-artisanpack-badge size="sm" color="info" leading-icon="eye">{{ __( 'Visible to customer' ) }}</x-artisanpack-badge>
                            @else
                                <x-artisanpack-badge size="sm" color="neutral" leading-icon="lock">{{ __( 'Internal' ) }}</x-artisanpack-badge>
                            @endif
                        </div>
                        @if ( $note['canDelete'] )
                            <x-artisanpack-button
                                variant="ghost"
                                color="neutral"
                                size="xs"
                                leading-icon="trash"
                                class="ec-note-delete"
                                wire:click="confirmDelete( {{ $note['id'] }} )"
                                wire:loading.attr="disabled"
                                wire:target="confirmDelete"
                                :aria-label="__( 'Delete note by :author', [ 'author' => $note['author'] ] )"
                                data-delete-note
                            />
                        @endif
                        <p class="ec-note-body">{{ $note['body'] }}</p>
                    </li>
                @endforeach
            </x-artisanpack-list>
        @endif
    </x-artisanpack-card>

    @if ( $confirmingDelete && null !== $deletingNote )
        @php
            $deleteDescription = null === $deletingNote['time']
                ? __( 'The note by :author will be removed for everyone.', [ 'author' => $deletingNote['author'] ] )
                : __( 'The note by :author from :time will be removed for everyone.', [ 'author' => $deletingNote['author'], 'time' => $deletingNote['time']->diffForHumans() ] );
        @endphp
        <x-artisanpack-dialog
            wire:model="confirmingDelete"
            size="sm"
            role="alertdialog"
            color="error"
            icon="trash"
            :title="__( 'Delete this note?' )"
            :description="$deleteDescription"
            :close-label="__( 'Close' )"
            data-delete-note-dialog
        >
            <x-slot:footer>
                <x-artisanpack-button variant="outline" color="neutral" wire:click="cancelDelete" data-autofocus>{{ __( 'Keep note' ) }}</x-artisanpack-button>
                <x-artisanpack-button
                    color="error"
                    wire:click="deleteNote( {{ (int) $deletingNote['id'] }} )"
                    wire:loading.attr="disabled"
                    wire:target="deleteNote"
                >{{ __( 'Delete' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
