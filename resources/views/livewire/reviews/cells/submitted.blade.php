{{-- Reviews cell (#125): when the review came in, muted. --}}
<span class="ec-muted-text">{{ null === $row->created_at ? '' : \ArtisanPackUI\Ecommerce\Support\LocalizedDate::format( $row->created_at ) }}</span>
