<div class="flex flex-wrap justify-end gap-1">
    @if ( $context['canView'] )
        <x-artisanpack-button size="sm" variant="ghost" icon="o-eye" wire:click="showReview( {{ $row->id }} )" :aria-label="__( 'Read the review by :author', [ 'author' => $row->author_name ] )" />
    @endif
    @if ( $context['canModerate'] )
        @include( 'ecommerce-admin::livewire.reviews.moderation-buttons', [ 'review' => $row, 'canDelete' => $context['canDelete'] ] )
    @elseif ( $context['canDelete'] )
        <x-artisanpack-button size="sm" variant="ghost" icon="o-trash" wire:click="confirmDelete( {{ $row->id }} )" :aria-label="__( 'Delete the review by :author', [ 'author' => $row->author_name ] )" />
    @endif
</div>
