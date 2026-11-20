{{-- Customers cell (#128): how many orders the customer has placed. --}}
<bdi dir="ltr">{{ \Illuminate\Support\Number::format( (int) $row->orders_count, locale: app()->getLocale() ) }}</bdi>
