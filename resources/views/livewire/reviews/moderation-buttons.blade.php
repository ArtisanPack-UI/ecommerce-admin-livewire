{{--
    Moderation buttons for one review: those that change its status, then delete.

    Expects $review and $canDelete.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php( $author = $review->author_name )
@if ( 'approved' !== $review->status )
    <x-artisanpack-button size="sm" color="success" icon="o-check" wire:click="moderate( {{ $review->id }}, 'approve' )" wire:loading.attr="disabled" :label="__( 'Approve' )" :aria-label="__( 'Approve the review by :author', [ 'author' => $author ] )" />
@endif
@if ( 'rejected' !== $review->status )
    <x-artisanpack-button size="sm" variant="outline" icon="o-x-mark" wire:click="startReject( {{ $review->id }} )" :label="__( 'Reject' )" :aria-label="__( 'Reject the review by :author', [ 'author' => $author ] )" />
@endif
@if ( 'spam' !== $review->status )
    <x-artisanpack-button size="sm" variant="ghost" icon="o-no-symbol" wire:click="moderate( {{ $review->id }}, 'spam' )" wire:loading.attr="disabled" :label="__( 'Spam' )" :aria-label="__( 'Mark the review by :author as spam', [ 'author' => $author ] )" />
@endif
@if ( 'pending' !== $review->status )
    <x-artisanpack-button size="sm" variant="ghost" icon="o-arrow-uturn-left" wire:click="moderate( {{ $review->id }}, 'requeue' )" wire:loading.attr="disabled" :label="__( 'Requeue' )" :aria-label="__( 'Requeue the review by :author for moderation', [ 'author' => $author ] )" />
@endif
@if ( $canDelete )
    <x-artisanpack-button size="sm" variant="ghost" icon="o-trash" wire:click="confirmDelete( {{ $review->id }} )" :aria-label="__( 'Delete the review by :author', [ 'author' => $author ] )" />
@endif
