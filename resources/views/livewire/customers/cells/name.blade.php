{{--
    Customers cell (#128): a decorative avatar and the customer's name,
    linked to the customer. A customer without a name reads "No name" in
    muted italics (never blank), and the link names them by email.
--}}
@php
    $customerName = \ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\Index::customerName( $row );
    $avatar       = \ArtisanPack\LivewireUiComponents\View\Components\Avatar::class;
    $linkLabel    = null === $customerName ? __( 'No name (:email)', [ 'email' => (string) $row->email ] ) : null;
@endphp
<span class="ec-customers-name" data-customer-name="{{ $row->getKey() }}">
    <x-artisanpack-avatar
        size="sm"
        :initials="null === $customerName ? '?' : $avatar::initialsFor( $customerName )"
        :color="null === $customerName ? 'neutral' : $avatar::colorFor( $customerName )"
    />
    @if ( null !== $context['showRoute'] )
        <x-artisanpack-link
            :href="route( $context['showRoute'], [ 'customer' => $row->getKey() ] )"
            :navigate="false"
            standalone
            :tone="null === $customerName ? 'muted' : 'primary'"
            :aria-label="$linkLabel"
            class="{{ \Illuminate\Support\Arr::toCssClasses( [ 'ec-customers-name-link', 'ec-customers-unnamed' => null === $customerName ] ) }}"
        >{{ $customerName ?? __( 'No name' ) }}</x-artisanpack-link>
    @else
        <span @class( [ 'ec-customers-name-text', 'ec-customers-unnamed' => null === $customerName ] )>{{ $customerName ?? __( 'No name' ) }}</span>
    @endif
</span>
