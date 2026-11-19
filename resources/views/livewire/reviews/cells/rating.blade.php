{{-- Reviews cell (#125): the stars, one image named "2 out of 5". --}}
<x-artisanpack-rating :value="max( 0, min( 5, (int) $row->rating ) )" readonly size="sm" />
