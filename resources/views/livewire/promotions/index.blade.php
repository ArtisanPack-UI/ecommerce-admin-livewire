{{--
    Promotions list (#130). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\Index.

    Every promotion on the shared Resource table (#79) with its source, the
    shared Promotion state badge (#92), its run window, uses against any
    limit, priority, and an accent badge when it is exclusive. New promotion
    is the page's one solid action. The bulk bar switches promotions on or
    off and deletes them, which is confirmed inline.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="ec-stack" data-promotions-index>
    <x-artisanpack-page-header :title="__( 'Promotions' )" :description="__( 'Discounts that apply on their own or with a coupon code.' )" :level="1">
        @if ( null !== $createUrl )
            <x-slot:actions>
                <x-artisanpack-button size="sm" leading-icon="plus" :href="$createUrl" data-new-promotion>{{ __( 'New promotion' ) }}</x-artisanpack-button>
            </x-slot:actions>
        @endif
    </x-artisanpack-page-header>

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'megaphone',
        'emptyTitle'       => __( 'No promotions yet' ),
        'emptyDescription' => __( 'Promotions give discounts automatically or through coupon codes.' ),
        'emptyActions'     => null !== $createUrl ? 'ecommerce-admin::livewire.promotions.empty-actions' : null,
        'searchHint'       => __( 'Search promotions' ),
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\Promotion $promotion ): string => (string) $promotion->name,
    ] )
</div>
