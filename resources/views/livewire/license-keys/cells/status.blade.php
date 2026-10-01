@php( $status = \ArtisanPackUI\EcommerceAdminLivewire\Livewire\LicenseKeys\Index::status( $row ) )
<x-artisanpack-badge
    :value="\ArtisanPackUI\EcommerceAdminLivewire\Livewire\LicenseKeys\Index::statuses()[ $status ]"
    class="badge-sm"
    :color="match ( $status ) { 'revoked' => 'error', 'expired' => 'warning', default => 'success' }"
    data-license-status="{{ $status }}"
/>
