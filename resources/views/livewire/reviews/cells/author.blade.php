<span class="block">{{ $row->author_name }}</span>
@if ( filled( $row->author_email ) )
    <span class="block text-xs opacity-75">{{ $row->author_email }}</span>
@endif
