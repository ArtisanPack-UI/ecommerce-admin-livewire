{{--
    Customers (#128). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\Index.

    Every customer on the shared Resource table (#79): an avatar and name
    linking to the customer, then email, order count, lifetime spend, last
    order, marketing consent and whether they have an account. The only bulk
    action is export; editing and deleting happen on the detail page.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="ec-stack" data-customers-index>
    <x-artisanpack-page-header :title="__( 'Customers' )" :description="__( 'Everyone who has ordered or created an account.' )" :level="1" />

    @include( 'ecommerce-admin::partials.resource-table', [
        'emptyIcon'        => 'users',
        'emptyTitle'       => __( 'No customers yet' ),
        'emptyDescription' => __( 'Customers appear here after their first order or when they create an account.' ),
        'searchHint'       => __( 'Search by name, email or phone' ),
        'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\Customer $customer ): string => \ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\Index::customerName( $customer ) ?? (string) $customer->email,
        'cellContext'      => [ 'showRoute' => $customerShowRoute ],
    ] )
</div>
