{{--
    Reviews (#125): the moderation buttons for one review, in the drawer's
    footer (and in its row when the user can moderate but not open the
    drawer). Approve is the soft success button, Reject an outline button
    that opens the reason dialog, and Spam, Back to pending and Delete are
    quieter. Each is named for the review's author ("Approve the review by
    Daniel Okafor"); a button for the review's current status is left out.

    Expects $review, $canModerate and $canDelete, and optionally $size
    (default "md").

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    $author = $review->author_name;
    $size ??= 'md';
@endphp
@if ( $canModerate )
    @if ( 'approved' !== $review->status )
        <x-artisanpack-button variant="soft" color="success" :size="$size" leading-icon="check" wire:click="moderate( {{ $review->id }}, 'approve' )" wire:loading.attr="disabled" wire:target="moderate" :aria-label="__( 'Approve the review by :author', [ 'author' => $author ] )" data-moderate="approve">{{ __( 'Approve' ) }}</x-artisanpack-button>
    @endif
    @if ( 'rejected' !== $review->status )
        <x-artisanpack-button variant="outline" color="neutral" :size="$size" leading-icon="circle-x" wire:click="startReject( {{ $review->id }} )" :aria-label="__( 'Reject the review by :author', [ 'author' => $author ] )" data-moderate="reject">{{ __( 'Reject' ) }}</x-artisanpack-button>
    @endif
    @if ( 'spam' !== $review->status )
        <x-artisanpack-button variant="ghost" color="neutral" :size="$size" wire:click="moderate( {{ $review->id }}, 'spam' )" wire:loading.attr="disabled" wire:target="moderate" :aria-label="__( 'Mark the review by :author as spam', [ 'author' => $author ] )" data-moderate="spam">{{ __( 'Spam' ) }}</x-artisanpack-button>
    @endif
    @if ( 'pending' !== $review->status )
        <x-artisanpack-button variant="ghost" color="neutral" :size="$size" leading-icon="undo-2" wire:click="moderate( {{ $review->id }}, 'requeue' )" wire:loading.attr="disabled" wire:target="moderate" :aria-label="__( 'Move the review by :author back to pending', [ 'author' => $author ] )" data-moderate="requeue">{{ __( 'Back to pending' ) }}</x-artisanpack-button>
    @endif
@endif
@if ( $canDelete )
    <x-artisanpack-button variant="ghost" color="error" :size="$size" leading-icon="trash" icon-only wire:click="confirmDelete( {{ $review->id }} )" :aria-label="__( 'Delete the review by :author', [ 'author' => $author ] )" data-moderate="delete" />
@endif
