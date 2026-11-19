{{--
    Products list cell (#119): a star named "Featured" (filled, accent) or
    "Not featured" (outline, subtle), so the state never rests on colour alone.
--}}
@if ( $row->is_featured )
    <span class="ec-products-star" role="img" aria-label="{{ __( 'Featured' ) }}" data-featured>
        <x-artisanpack-icon name="star" aria-hidden="true" />
    </span>
@else
    <span class="ec-products-star" role="img" aria-label="{{ __( 'Not featured' ) }}" data-not-featured>
        <x-artisanpack-icon name="star" aria-hidden="true" />
    </span>
@endif
