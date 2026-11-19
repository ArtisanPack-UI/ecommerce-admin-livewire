{{--
    Reviews cell (#125): Read (opens the drawer, where the review is
    moderated) and Delete, icon-only and named for the author. A user who
    may moderate but not open the drawer gets the moderation buttons here
    instead.
--}}
<div class="ec-reviews-row-actions">
    @if ( $context['canView'] )
        <x-artisanpack-button variant="ghost" color="neutral" size="xs" leading-icon="eye" wire:click="showReview( {{ $row->id }} )" :aria-label="__( 'Read the review by :author', [ 'author' => $row->author_name ] )" data-read-review="{{ $row->id }}" />
        @if ( $context['canDelete'] )
            <x-artisanpack-button variant="ghost" color="neutral" size="xs" leading-icon="trash" wire:click="confirmDelete( {{ $row->id }} )" :aria-label="__( 'Delete the review by :author', [ 'author' => $row->author_name ] )" />
        @endif
    @else
        @include( 'ecommerce-admin::livewire.reviews.moderation-buttons', [ 'review' => $row, 'canModerate' => $context['canModerate'], 'canDelete' => $context['canDelete'], 'size' => 'xs' ] )
    @endif
</div>
