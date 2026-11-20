{{--
    A delivery's event (#137): the event key in a mono badge, then the
    delivery's id in muted mono.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      2.0.0
--}}
<span class="ec-webhook-detail-pair">
    <x-artisanpack-badge color="neutral" size="sm"><bdi dir="ltr" class="ec-webhook-detail-code">{{ $row->event }}</bdi></x-artisanpack-badge>
    <bdi dir="ltr" class="ec-webhook-detail-code ec-muted">#{{ $row->getKey() }}</bdi>
</span>
