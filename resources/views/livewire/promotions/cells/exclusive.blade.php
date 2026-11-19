{{--
    Promotions list cell (#130): exclusive promotions get the page's one
    accent badge, because they change how stacking works. The rest show a
    muted dash that reads "Not exclusive".
--}}
@if ( $row->is_exclusive )
    <x-artisanpack-badge variant="soft" color="accent" size="sm" data-promotion-exclusive="true">{{ __( 'Exclusive' ) }}</x-artisanpack-badge>
@else
    <span class="ec-muted" role="img" aria-label="{{ __( 'Not exclusive' ) }}">&mdash;</span>
@endif
