{{--
    Reviews moderation queue. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Reviews\Index.

    Review text is customer input: it is only ever printed escaped.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Reviews\Index;
@endphp
<div>
    <x-artisanpack-header :title="__( 'Reviews' )" :level="1" separator />

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'o-star',
        'emptyTitle'       => __( 'No reviews yet' ),
        'emptyDescription' => __( 'Reviews customers leave on products appear here for moderation.' ),
        'bulkControls'     => 'ecommerce-admin::livewire.reviews.bulk-controls',
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\ProductReview $review ): string => __( 'Review by :author', [ 'author' => $review->author_name ] ),
        'cellContext'      => [ 'canModerate' => $canModerate, 'canDelete' => $canDelete, 'canView' => $canView ],
    ] )

    <x-artisanpack-drawer wire:model="viewing" :title="__( 'Review' )" right separator with-close-button close-on-escape class="w-full max-w-xl">
        @if ( null !== $reviewing )
            <article class="flex flex-col gap-4" data-review-detail="{{ $reviewing->id }}">
                <div class="flex flex-wrap items-center gap-2">
                    <x-artisanpack-ec-status-badge type="review" :value="$reviewing->status" />
                    @if ( $reviewing->is_verified_purchase )
                        <x-artisanpack-badge :value="__( 'Verified purchase' )" color="success" class="badge-sm" />
                    @endif
                </div>

                <dl class="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1 text-sm">
                    <dt class="font-semibold">{{ __( 'Product' ) }}</dt>
                    <dd>{{ $reviewing->product?->name ?? __( 'Deleted product' ) }}</dd>
                    <dt class="font-semibold">{{ __( 'Rating' ) }}</dt>
                    <dd>{{ __( ':rating out of 5', [ 'rating' => $reviewing->rating ] ) }}</dd>
                    <dt class="font-semibold">{{ __( 'Author' ) }}</dt>
                    <dd>{{ $reviewing->author_name }}@if ( $reviewing->author_email ) &lt;{{ $reviewing->author_email }}&gt;@endif</dd>
                    @if ( null !== $reviewing->order )
                        <dt class="font-semibold">{{ __( 'Order' ) }}</dt>
                        <dd>#{{ $reviewing->order->order_number }}</dd>
                    @endif
                    <dt class="font-semibold">{{ __( 'Submitted' ) }}</dt>
                    <dd>{{ null === $reviewing->created_at ? '' : \ArtisanPackUI\Ecommerce\Support\LocalizedDate::format( $reviewing->created_at ) }}</dd>
                </dl>

                @if ( filled( $reviewing->title ) )
                    <h3 class="text-lg font-semibold">{{ $reviewing->title }}</h3>
                @endif
                <div class="whitespace-pre-line break-words" data-review-body>{{ $reviewing->body }}</div>

                @if ( [] !== $reviewMedia )
                    <section aria-labelledby="review-media-heading">
                        <h3 id="review-media-heading" class="mb-2 font-semibold">{{ __( 'Media' ) }}</h3>
                        <ul class="flex list-none flex-wrap gap-2">
                            @foreach ( $reviewMedia as $media )
                                <li wire:key="review-media-{{ $media['id'] }}">
                                    @if ( null !== $media['url'] )
                                        <a href="{{ $media['url'] }}" target="_blank" rel="noopener noreferrer">
                                            <img src="{{ $media['url'] }}" alt="{{ __( 'Photo :number from the review', [ 'number' => $loop->iteration ] ) }}" class="size-24 rounded object-cover" />
                                        </a>
                                    @else
                                        <span class="text-sm">{{ __( 'Media library file #:id', [ 'id' => $media['id'] ] ) }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ( $canModerate )
                    <div class="flex flex-wrap gap-2">
                        @include( 'ecommerce-admin::livewire.reviews.moderation-buttons', [ 'review' => $reviewing, 'canDelete' => $canDelete ] )
                    </div>
                @endif
            </article>
        @endif
    </x-artisanpack-drawer>

    @if ( $rejecting )
        <x-artisanpack-modal wire:model="rejecting" :title="__( 'Reject review' )" separator>
            <form wire:submit="reject" id="review-reject-form" data-reject-form>
                <x-artisanpack-textarea id="review-reject-reason" :label="__( 'Reason' )" :hint="__( 'For your team. The reviewer is not told.' )" wire:model="rejectReason" rows="3" required />
            </form>

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" wire:click="closeReject" :label="__( 'Cancel' )" />
                <x-artisanpack-button type="submit" form="review-reject-form" color="error" wire:loading.attr="disabled" :label="__( 'Reject review' )" />
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif
</div>
