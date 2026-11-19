{{-- Digital files cell (#126): when the file last changed, muted. --}}
@if ( null !== $row->updated_at )
    <span class="ec-cell-muted ec-nowrap">{{ \ArtisanPackUI\Ecommerce\Support\LocalizedDate::format( $row->updated_at ) }}</span>
@endif
