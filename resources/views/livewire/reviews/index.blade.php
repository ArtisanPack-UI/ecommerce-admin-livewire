{{--
    Reviews (#125). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Reviews\Index.

    The moderation queue on the shared Resource table (#79): the rating as
    stars named "2 out of 5", the author, a Verified badge, the review clamped
    to two lines and its status. Rows open the review drawer and delete it;
    approving, rejecting and marking spam happen in the drawer, or across the
    selection from the bulk bar, where Approve is the soft success button and
    the reason field feeds Reject (bulk-controls). The drawer shows the whole
    review and its photos with the moderation buttons in its footer. Reject
    asks for an optional private reason in a small dialog.

    Review text is customer input: it is only ever printed escaped.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    $productRoute = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'products.edit';
    $orderRoute   = \ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav::ROUTE_PREFIX . 'orders.show';
@endphp
<div class="ec-stack" data-reviews-index>
    <x-artisanpack-page-header :title="__( 'Reviews' )" :description="__( 'What customers say about your products, waiting for you to approve before it goes live.' )" :level="1" />

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'         => 'star',
        'emptyTitle'        => __( 'No reviews yet' ),
        'emptyDescription'  => __( 'Reviews customers leave on products appear here for moderation.' ),
        'searchHint'        => __( 'Search reviews, products or authors' ),
        'bulkControls'      => 'ecommerce-admin::livewire.reviews.bulk-controls',
        'bulkInlineActions' => [ 'approve' ],
        'rowLabel'          => static fn ( \ArtisanPackUI\Ecommerce\Models\ProductReview $review ): string => (string) $review->author_name,
        'cellContext'       => [ 'canModerate' => $canModerate, 'canDelete' => $canDelete, 'canView' => $canView ],
    ] )

    <x-artisanpack-drawer
        wire:model="viewing"
        :title="null === $reviewing ? __( 'Review' ) : __( 'Review by :author', [ 'author' => $reviewing->author_name ] )"
        side="end"
        size="md"
        :close-label="__( 'Close' )"
    >
        @if ( null !== $reviewing )
            <article class="ec-stack" data-review-detail="{{ $reviewing->id }}">
                <div class="ec-reviews-badges">
                    <x-artisanpack-ec-status-badge type="review" :value="$reviewing->status" />
                    @if ( $reviewing->is_verified_purchase )
                        <x-artisanpack-badge color="success" leading-icon="check" data-verified>{{ __( 'Verified purchase' ) }}</x-artisanpack-badge>
                    @endif
                </div>

                <dl class="ec-dl">
                    <dt>{{ __( 'Product' ) }}</dt>
                    <dd>
                        @if ( null === $reviewing->product )
                            <span class="ec-muted-text">{{ __( 'Deleted product' ) }}</span>
                        @elseif ( \Illuminate\Support\Facades\Route::has( $productRoute ) )
                            <a href="{{ route( $productRoute, [ 'product' => $reviewing->product_id ] ) }}" class="apui-link">{{ $reviewing->product->name }}</a>
                        @else
                            {{ $reviewing->product->name }}
                        @endif
                    </dd>
                    <dt>{{ __( 'Rating' ) }}</dt>
                    <dd><x-artisanpack-rating :value="max( 0, min( 5, (int) $reviewing->rating ) )" readonly size="md" /></dd>
                    <dt>{{ __( 'Author' ) }}</dt>
                    <dd>
                        {{ $reviewing->author_name }}
                        @if ( filled( $reviewing->author_email ) )
                            <span class="ec-reviews-email"><bdi dir="ltr">{{ $reviewing->author_email }}</bdi></span>
                        @endif
                    </dd>
                    @if ( null !== $reviewing->order )
                        <dt>{{ __( 'Order' ) }}</dt>
                        <dd>
                            @if ( \Illuminate\Support\Facades\Route::has( $orderRoute ) )
                                <a href="{{ route( $orderRoute, [ 'order' => $reviewing->order->id ] ) }}" class="apui-link"><bdi dir="ltr" class="ec-mono">#{{ $reviewing->order->order_number }}</bdi></a>
                            @else
                                <bdi dir="ltr" class="ec-mono">#{{ $reviewing->order->order_number }}</bdi>
                            @endif
                        </dd>
                    @endif
                    <dt>{{ __( 'Submitted' ) }}</dt>
                    <dd>{{ null === $reviewing->created_at ? '' : \ArtisanPackUI\Ecommerce\Support\LocalizedDate::format( $reviewing->created_at ) }}</dd>
                </dl>

                <div class="ec-reviews-text">
                    @if ( filled( $reviewing->title ) )
                        <p class="ec-reviews-title">{{ $reviewing->title }}</p>
                    @endif
                    <p class="ec-reviews-body" data-review-body>{{ $reviewing->body }}</p>
                </div>

                @if ( [] !== $reviewMedia )
                    <section class="ec-reviews-media" aria-labelledby="review-media-heading">
                        <h3 id="review-media-heading" class="ec-reviews-media-heading">{{ __( 'Media' ) }}</h3>
                        <ul class="ec-reviews-media-list" role="list">
                            @foreach ( $reviewMedia as $media )
                                <li wire:key="review-media-{{ $media['id'] }}">
                                    @if ( null !== $media['url'] )
                                        <a href="{{ $media['url'] }}" class="ec-reviews-photo" target="_blank" rel="noopener noreferrer">
                                            <img src="{{ $media['url'] }}" alt="{{ __( 'Photo :number from the review', [ 'number' => $loop->iteration ] ) }}" />
                                            <span class="apui-sr-only">{{ __( '(opens in a new tab)' ) }}</span>
                                        </a>
                                    @else
                                        <span class="ec-muted-text">{{ __( 'Media library file #:id', [ 'id' => $media['id'] ] ) }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </article>
        @endif

        @if ( null !== $reviewing && ( $canModerate || $canDelete ) )
            <x-slot:footer>
                <div class="ec-reviews-moderation" data-review-moderation>
                    @include( 'ecommerce-admin::livewire.reviews.moderation-buttons', [ 'review' => $reviewing, 'canModerate' => $canModerate, 'canDelete' => $canDelete, 'size' => 'md' ] )
                </div>
            </x-slot:footer>
        @endif
    </x-artisanpack-drawer>

    @if ( $rejecting )
        <x-artisanpack-dialog
            wire:model="rejecting"
            size="sm"
            color="error"
            icon="circle-x"
            :title="__( 'Reject review' )"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
        >
            <form wire:submit="reject" id="review-reject-form" data-reject-form>
                <x-artisanpack-textarea
                    id="review-reject-reason"
                    :label="__( 'Reason (optional)' )"
                    :hint="__( 'For your team. The reviewer is not told.' )"
                    wire:model="rejectReason"
                    :rows="3"
                    data-autofocus
                />
            </form>

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="closeReject">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                <x-artisanpack-button type="submit" form="review-reject-form" color="error" wire:loading.attr="disabled" wire:target="reject" data-reject-submit>{{ __( 'Reject review' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
