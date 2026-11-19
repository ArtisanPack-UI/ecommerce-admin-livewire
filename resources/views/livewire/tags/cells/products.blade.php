{{--
    Tags cell (#123): how many products carry the tag, linked to the
    Products list filtered by it. A tag with none, or a row being renamed,
    shows the plain number.
--}}
@php( $productCount = (int) $row->products_count )
@if ( $productCount > 0 && null !== $context['productsUrl'] && $context['editingId'] !== (int) $row->getKey() )
    <x-artisanpack-link
        :href="$context['productsUrl'] . '?' . http_build_query( [ 'filters' => [ 'tag' => $row->getKey() ] ] )"
        :navigate="false"
        :aria-label="trans_choice( ':count product tagged :name|:count products tagged :name', $productCount, [ 'count' => $productCount, 'name' => $row->name ] )"
        data-tag-products="{{ $row->getKey() }}"
    ><bdi dir="ltr">{{ $productCount }}</bdi></x-artisanpack-link>
@else
    <bdi dir="ltr">{{ $productCount }}</bdi>
@endif
