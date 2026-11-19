{{--
    License keys cell (#127): Active (success), Expired (neutral) or Revoked
    (error), each with a dot and its word, so colour never carries it alone.
--}}
@php( $status = \ArtisanPackUI\EcommerceAdminLivewire\Livewire\LicenseKeys\Index::status( $row ) )
<x-artisanpack-badge
    size="sm"
    dot
    :color="match ( $status ) { 'revoked' => 'error', 'expired' => 'neutral', default => 'success' }"
    data-license-status="{{ $status }}"
>{{ \ArtisanPackUI\EcommerceAdminLivewire\Livewire\LicenseKeys\Index::statuses()[ $status ] }}</x-artisanpack-badge>
