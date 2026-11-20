{{--
    Promotion usage panel (#112). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\UsagePanel.

    What the promotion has given away: a small Stat per currency with the
    total discounted and how many orders, then each order with its customer,
    the amount and the date, newest first. Discount amounts keep the minus
    sign but stay neutral in colour, because a discount isn't an error.
    Deleted orders and guests still get a row: "Deleted order" in muted
    italics with no link, "Guest" muted.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
@endphp
<div class="ec-stack" data-promotion-usage>
    @if ( 0 === $usages->total() )
        <x-artisanpack-ec-admin-empty-state
            size="sm"
            class="ec-empty-sunken"
            :heading-level="3"
            icon="chart-column"
            :title="__( 'Not used yet' )"
            :description="__( 'Orders that this promotion discounts appear here.' )"
        />
    @else
        <div class="ec-usage-totals" role="group" aria-label="{{ __( 'Total discounted' ) }}" data-usage-totals>
            @foreach ( $totals as $total )
                <x-artisanpack-stat
                    size="sm"
                    class="ec-usage-total"
                    icon="percent"
                    :label="__( 'Total discounted' )"
                    :description="trans_choice( 'Discounted on :count order in :currency|Discounted on :count orders in :currency', (int) $total->uses, [ 'count' => (int) $total->uses, 'currency' => $total->currency ] )"
                    wire:key="usage-total-{{ $total->currency }}"
                    data-usage-total="{{ $total->currency }}"
                ><bdi dir="ltr">{{ MoneyFormatter::format( (int) $total->discounted, (string) $total->currency ) }}</bdi></x-artisanpack-stat>
            @endforeach
        </div>

        <div class="apui-table ec-table" data-surface="default">
            <div data-scope="table" data-part="scroll">
                <table data-scope="table" data-part="table" data-usage-orders>
                    <caption class="apui-sr-only">{{ __( 'Orders this promotion discounted' ) }}</caption>
                    <thead>
                        <tr>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Order' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Customer' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Discounted' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Date' ) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ( $usages as $usage )
                            @php( $usageCustomer = $customerName( $usage ) )
                            <tr data-scope="table" data-part="row" wire:key="promotion-usage-{{ $usage->id }}">
                                <td data-scope="table" data-part="cell">
                                    @if ( null === $usage->order )
                                        <span class="ec-cell-muted ec-deleted" data-deleted-order>{{ __( 'Deleted order' ) }}</span>
                                    @elseif ( null !== $orderRoute )
                                        <x-artisanpack-link :href="route( $orderRoute, [ 'order' => $usage->order_id ] )" :navigate="false" standalone><bdi dir="ltr" class="ec-mono">#{{ $usage->order->order_number }}</bdi></x-artisanpack-link>
                                    @else
                                        <bdi dir="ltr" class="ec-mono">#{{ $usage->order->order_number }}</bdi>
                                    @endif
                                </td>
                                <td data-scope="table" data-part="cell">
                                    @if ( null === $usageCustomer )
                                        <span class="ec-cell-muted" data-guest>{{ __( 'Guest' ) }}</span>
                                    @elseif ( ! $canSeeNames )
                                        {{ __( 'Customer' ) }}
                                    @elseif ( null !== $customerRoute )
                                        <x-artisanpack-link :href="route( $customerRoute, [ 'customer' => $usage->customer_id ] )" :navigate="false">{{ $usageCustomer }}</x-artisanpack-link>
                                    @else
                                        {{ $usageCustomer }}
                                    @endif
                                </td>
                                <td data-scope="table" data-part="cell" data-numeric><x-artisanpack-ec-admin-money :amount="-1 * (int) $usage->amount_discounted" :currency="$usage->currency" neutral /></td>
                                <td data-scope="table" data-part="cell" class="ec-cell-muted ec-nowrap">
                                    @if ( null !== $usage->created_at )
                                        <time datetime="{{ $usage->created_at->toIso8601String() }}">{{ LocalizedDate::format( $usage->created_at ) }}</time>
                                    @else
                                        <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div data-scope="table" data-part="footer">
                <x-artisanpack-pagination :rows="$usages" summary :summary-label="__( 'Showing {from} to {to} of {total} results' )" />
            </div>
        </div>
    @endif
</div>
