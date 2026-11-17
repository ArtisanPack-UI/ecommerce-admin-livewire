{{--
    Moderation buttons for one review: those that change its status, then delete.

    Expects $review and $canDelete.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php( $author = $review->author_name )
@if ( 'approved' !== $review->status )
    <x-artisanpack-button size="sm" color="success" leading-icon="o-check" wire:click="moderate( {{ $review->id }}, 'approve' )" wire:loading.attr="disabled" :aria-label="__( 'Approve the review by :author', [ 'author' => $author ] )">{{ __( 'Approve' ) }}</x-artisanpack-button>
@endif
@if ( 'rejected' !== $review->status )
    <x-artisanpack-button size="sm" variant="outline" leading-icon="o-x-mark" wire:click="startReject( {{ $review->id }} )" :aria-label="__( 'Reject the review by :author', [ 'author' => $author ] )">{{ __( 'Reject' ) }}</x-artisanpack-button>
@endif
@if ( 'spam' !== $review->status )
    <x-artisanpack-button size="sm" variant="ghost" leading-icon="o-no-symbol" wire:click="moderate( {{ $review->id }}, 'spam' )" wire:loading.attr="disabled" :aria-label="__( 'Mark the review by :author as spam', [ 'author' => $author ] )">{{ __( 'Spam' ) }}</x-artisanpack-button>
@endif
@if ( 'pending' !== $review->status )
    <x-artisanpack-button size="sm" variant="ghost" leading-icon="o-arrow-uturn-left" wire:click="moderate( {{ $review->id }}, 'requeue' )" wire:loading.attr="disabled" :aria-label="__( 'Requeue the review by :author for moderation', [ 'author' => $author ] )">{{ __( 'Requeue' ) }}</x-artisanpack-button>
@endif
@if ( $canDelete )
    <x-artisanpack-button size="sm" variant="ghost" leading-icon="o-trash" wire:click="confirmDelete( {{ $review->id }} )" :aria-label="__( 'Delete the review by :author', [ 'author' => $author ] )" />
@endif
