{{--
    A promotion's usage. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\UsagePanel.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
@endphp
<div class="flex flex-col gap-4" data-promotion-usage>
    @if ( 0 === $usages->total() )
        <x-artisanpack-ec-empty-state icon="o-chart-bar" :title="__( 'Not used yet' )" :description="__( 'Orders that this promotion discounts appear here.' )" />
    @else
        <dl class="flex flex-wrap gap-3" aria-label="{{ __( 'Total discounted' ) }}" data-usage-totals>
            @foreach ( $totals as $total )
                <div class="rounded-box border border-base-content/10 p-3" wire:key="usage-total-{{ $total->currency }}">
                    <dt class="text-sm opacity-75">{{ trans_choice( 'Discounted on :count order in :currency|Discounted on :count orders in :currency', (int) $total->uses, [ 'count' => (int) $total->uses, 'currency' => $total->currency ] ) }}</dt>
                    <dd class="text-lg font-semibold">{{ MoneyFormatter::format( (int) $total->discounted, (string) $total->currency ) }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="overflow-x-auto">
            <table class="table table-sm">
                <caption class="sr-only">{{ __( 'Orders this promotion discounted' ) }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __( 'Order' ) }}</th>
                        <th scope="col">{{ __( 'Customer' ) }}</th>
                        <th scope="col" class="text-end">{{ __( 'Discounted' ) }}</th>
                        <th scope="col">{{ __( 'Date' ) }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ( $usages as $usage )
                        <tr wire:key="promotion-usage-{{ $usage->id }}">
                            <td>
                                @if ( null === $usage->order )
                                    {{ __( 'Deleted order' ) }}
                                @elseif ( null !== $orderRoute )
                                    <a href="{{ route( $orderRoute, [ 'order' => $usage->order_id ] ) }}" class="link link-hover font-semibold">#{{ $usage->order->order_number }}</a>
                                @else
                                    #{{ $usage->order->order_number }}
                                @endif
                            </td>
                            <td>
                                @php( $usageCustomer = $customerName( $usage ) )
                                @if ( null === $usageCustomer )
                                    {{ __( 'Guest' ) }}
                                @elseif ( ! $canSeeNames )
                                    {{ __( 'Customer' ) }}
                                @elseif ( null !== $customerRoute )
                                    <a href="{{ route( $customerRoute, [ 'customer' => $usage->customer_id ] ) }}" class="link link-hover">{{ $usageCustomer }}</a>
                                @else
                                    {{ $usageCustomer }}
                                @endif
                            </td>
                            <td class="text-end"><x-artisanpack-ec-money :amount="(int) $usage->amount_discounted" :currency="$usage->currency" /></td>
                            <td>{{ null === $usage->created_at ? '—' : LocalizedDate::format( $usage->created_at ) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-artisanpack-pagination :rows="$usages" hide-per-page :page-info-template="__( 'Showing {from} to {to} of {total} results' )" />
    @endif
</div>
