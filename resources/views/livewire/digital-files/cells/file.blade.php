{{--
    Digital files cell (#126): where the file lives, left to right in
    monospace: "disk:path", or "Media library · file name" (the media id
    when the name isn't known). One line, cut short with an ellipsis; the
    title holds it in full.
--}}
@php
    $mediaName = null === $row->media_id ? null : ( $context['mediaNames'][ (int) $row->media_id ] ?? null );
    $location  = null === $mediaName ? \ArtisanPackUI\EcommerceAdminLivewire\Livewire\DigitalFiles\Index::location( $row ) : __( 'Media library' ) . ' · ' . $mediaName;
@endphp
<bdi dir="ltr" class="ec-mono ec-digital-files-location" title="{{ $location }}" data-file-location>{{ $location }}</bdi>
