{{--
    Customers table. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div>
    <x-artisanpack-header :title="__( 'Customers' )" :level="1" separator />

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'o-users',
        'emptyTitle'       => __( 'No customers yet' ),
        'emptyDescription' => __( 'Customers appear here after their first order or when they create an account.' ),
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\Customer $customer ): string => \ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\Index::customerName( $customer ) ?? (string) $customer->email,
    ] )
</div>
