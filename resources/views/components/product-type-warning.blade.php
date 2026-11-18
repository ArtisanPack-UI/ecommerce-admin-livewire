{{--
    Product type warning (#107). See ArtisanPackUI\EcommerceAdminLivewire\View\Components\ProductTypeWarning.

    A warning Alert that explains why a product can't be edited: the
    satellite that provides its type isn't installed. It names the type and
    says what keeps working. The product form places it directly under the
    page header, above the tabs, so it's read before any field.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<x-artisanpack-alert
    {{ $attributes }}
    data-product-type-warning
    color="warning"
    icon="triangle-alert"
    :title="__( 'This product is read-only' )"
    :description="__( 'Its type, “:type”, is provided by a plugin that is no longer installed. Reinstall it to edit this product; orders and stock keep working.', [ 'type' => $type ] )"
    role="status"
/>
