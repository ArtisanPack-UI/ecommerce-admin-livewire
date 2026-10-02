{{--
    Promotions table. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div>
    <x-artisanpack-header :title="__( 'Promotions' )" :level="1" separator>
        @if ( null !== $createUrl )
            <x-slot:actions>
                <x-artisanpack-button color="primary" icon="o-plus" :link="$createUrl" :label="__( 'New promotion' )" />
            </x-slot:actions>
        @endif
    </x-artisanpack-header>

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'o-megaphone',
        'emptyTitle'       => __( 'No promotions yet' ),
        'emptyDescription' => __( 'Promotions give discounts automatically or through coupon codes.' ),
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\Promotion $promotion ): string => (string) $promotion->name,
    ] )
</div>
