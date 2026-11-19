{{-- Inventory cell (#124): units on hand or reserved, kept left to right so a negative count reads correctly in RTL. --}}
<bdi dir="ltr" class="ec-money">{{ (int) ( 'reserved' === $column['key'] ? $row->quantity_reserved : $row->quantity_on_hand ) }}</bdi>
