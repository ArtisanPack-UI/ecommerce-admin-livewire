{{-- Customers cell (#128): lifetime spend in the customer's currency ($0.00 with no orders). --}}
<x-artisanpack-ec-admin-money :amount="(int) $row->total_spent_amount" :currency="\ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\Index::spentCurrency( $row )" />
