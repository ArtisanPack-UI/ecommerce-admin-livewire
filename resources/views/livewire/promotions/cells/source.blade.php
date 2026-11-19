{{--
    Promotions list cell (#130): where the promotion comes from, as a small
    neutral badge. Coupon and automatic promotions carry the icons from
    Form::sourceIcon(), shared with the promotion form's source control;
    other registered sources show their label alone.
--}}
<x-artisanpack-badge variant="soft" color="neutral" size="sm" :leading-icon="\ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\Form::sourceIcon( (string) $row->source_type )" data-promotion-source="{{ $row->source_type }}">{{ \ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\Index::sourceLabel( (string) $row->source_type ) }}</x-artisanpack-badge>
