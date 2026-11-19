{{--
    Customer detail (#129). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\Show.

    The page header carries the name; the account and marketing badges
    follow the h1 (the header's meta slot would put them before it), so
    they're read before anything else. Five small Stats come next: lifetime
    value, orders, average order, and the first and last order dates.

    Below, the Contact card sits in the side column and the tabs from the
    customer tab registry in the main one. Editing swaps the Contact card for
    the form in place; the tabs stay put. The side column is first in the DOM,
    so below 1024px Contact sits above the tabs and the tab order matches
    what's seen.

    Deleting opens an alert dialog that splits what is erased from what is
    kept. Delete customer stays disabled until the customer's email is typed
    back; the server checks it again.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;

    $hasAccount = null !== $customer->user_id;
@endphp
<div class="ec-stack" data-customer-detail>
    <x-artisanpack-page-header :title="$name ?? $customer->email" :level="1">
        <x-slot:actions>
            @if ( null !== $indexUrl )
                <x-artisanpack-button variant="ghost" color="neutral" size="sm" :href="$indexUrl">{{ __( 'All customers' ) }}</x-artisanpack-button>
            @endif
            @if ( $canUpdate && ! $editing )
                <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="pencil" wire:click="startEdit" wire:loading.attr="disabled" wire:target="startEdit" data-edit-customer>{{ __( 'Edit' ) }}</x-artisanpack-button>
            @endif
            @if ( $canDelete )
                <x-artisanpack-button variant="outline" color="error" size="sm" leading-icon="trash" wire:click="startDelete" wire:loading.attr="disabled" wire:target="startDelete" data-delete-customer data-focus-key="customer-delete">{{ __( 'Delete' ) }}</x-artisanpack-button>
            @endif
        </x-slot:actions>
    </x-artisanpack-page-header>

    <div class="ec-badge-row ec-order-badges" role="group" aria-label="{{ __( 'Customer status' ) }}" data-customer-badges>
        <x-artisanpack-badge color="neutral" :leading-icon="$hasAccount ? 'user' : null" data-customer-account>{{ $hasAccount ? __( 'Has an account' ) : __( 'Guest' ) }}</x-artisanpack-badge>
        <x-artisanpack-badge :color="$customer->accepts_marketing ? 'success' : 'neutral'" :leading-icon="$customer->accepts_marketing ? 'check' : null" data-customer-marketing>{{ $customer->accepts_marketing ? __( 'Subscribed to marketing' ) : __( 'Not subscribed to marketing' ) }}</x-artisanpack-badge>
    </div>

    <ul class="ec-kpis ec-customer-stats" data-customer-stats>
        <li class="ec-kpi">
            <x-artisanpack-stat size="sm" :label="__( 'Lifetime value' )" icon="banknote"><bdi dir="ltr" data-stat="lifetime-value">{{ MoneyFormatter::format( $stats['lifetimeValue'], $stats['currency'] ) }}</bdi></x-artisanpack-stat>
        </li>
        <li class="ec-kpi">
            <x-artisanpack-stat size="sm" :label="__( 'Orders' )" icon="shopping-bag"><bdi dir="ltr" data-stat="order-count">{{ $stats['orderCount'] }}</bdi></x-artisanpack-stat>
        </li>
        <li class="ec-kpi">
            <x-artisanpack-stat size="sm" :label="__( 'Average order value' )" icon="chart-column"><bdi dir="ltr" data-stat="average-order-value">{{ null === $stats['averageOrderValue'] ? '—' : MoneyFormatter::format( $stats['averageOrderValue'], $stats['currency'] ) }}</bdi></x-artisanpack-stat>
        </li>
        <li class="ec-kpi">
            <x-artisanpack-stat size="sm" :label="__( 'First order' )" icon="calendar"><span data-stat="first-order">{{ null === $stats['firstOrderAt'] ? __( 'None yet' ) : LocalizedDate::format( $stats['firstOrderAt'] ) }}</span></x-artisanpack-stat>
        </li>
        <li class="ec-kpi">
            <x-artisanpack-stat size="sm" :label="__( 'Last order' )" icon="clock"><span data-stat="last-order">{{ null === $stats['lastOrderAt'] ? __( 'None yet' ) : LocalizedDate::format( $stats['lastOrderAt'] ) }}</span></x-artisanpack-stat>
        </li>
    </ul>

    {{-- The cards' titles are h3s (the Card component's level); this keeps the outline from jumping from the page's h1. --}}
    <h2 class="apui-sr-only">{{ __( 'Customer details' ) }}</h2>

    <div class="ec-order-detail">
        <div class="ec-order-col ec-order-col-side" data-customer-column="side">
            @if ( $editing )
                <x-artisanpack-card :title="__( 'Edit customer' )" data-customer-details-form>
                    <form
                        wire:submit="saveDetails"
                        id="customer-details-form"
                        class="ec-field-grid"
                        x-data
                        x-init="$nextTick( () => $el.querySelector( 'input' )?.focus() )"
                    >
                        <x-artisanpack-input id="customer-first-name" :label="__( 'First name' )" autocomplete="off" wire:model="firstName" />
                        <x-artisanpack-input id="customer-last-name" :label="__( 'Last name' )" autocomplete="off" wire:model="lastName" />
                        <x-artisanpack-input id="customer-phone" class="ec-span-2" type="tel" dir="ltr" :label="__( 'Phone' )" autocomplete="off" wire:model="phone" />
                        <x-artisanpack-toggle
                            id="customer-accepts-marketing"
                            class="ec-span-2"
                            :label="__( 'Accepts marketing' )"
                            :description="null === $customer->accepts_marketing_at
                                ? __( 'Turning this on records today as the date of consent.' )
                                : __( 'Consent recorded :date. Turning this off clears it.', [ 'date' => LocalizedDate::format( $customer->accepts_marketing_at ) ] )"
                            wire:model="acceptsMarketing"
                        />
                    </form>

                    <x-slot:footer>
                        <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelEdit" data-focus-return="customer-edit">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                        <x-artisanpack-button type="submit" form="customer-details-form" wire:loading.attr="disabled" wire:target="saveDetails" data-focus-return="customer-edit">{{ __( 'Save' ) }}</x-artisanpack-button>
                    </x-slot:footer>
                </x-artisanpack-card>
            @else
                <x-artisanpack-card :title="__( 'Contact' )" data-customer-contact>
                    @if ( $canUpdate )
                        <x-slot:actions>
                            <x-artisanpack-button
                                variant="ghost"
                                color="neutral"
                                size="xs"
                                leading-icon="pencil"
                                wire:click="startEdit"
                                wire:loading.attr="disabled"
                                wire:target="startEdit"
                                :aria-label="__( 'Edit contact details' )"
                                data-edit-customer
                                data-focus-key="customer-edit"
                            >{{ __( 'Edit' ) }}</x-artisanpack-button>
                        </x-slot:actions>
                    @endif

                    <dl class="ec-dl">
                        <dt>{{ __( 'Email' ) }}</dt>
                        <dd><x-artisanpack-link :href="'mailto:' . $customer->email" :navigate="false"><bdi dir="ltr">{{ $customer->email }}</bdi></x-artisanpack-link></dd>
                        <dt>{{ __( 'Phone' ) }}</dt>
                        <dd>
                            @if ( null !== $customer->phone && '' !== $customer->phone )
                                <bdi dir="ltr">{{ $customer->phone }}</bdi>
                            @else
                                <span class="ec-muted">{{ __( 'None' ) }}</span>
                            @endif
                        </dd>
                        <dt>{{ __( 'Account' ) }}</dt>
                        <dd data-customer-account-link>
                            @if ( $hasAccount )
                                {{ __( 'Linked to user #:id', [ 'id' => $customer->user_id ] ) }}
                            @else
                                <span class="ec-muted">{{ __( 'Guest, no account' ) }}</span>
                            @endif
                        </dd>
                        <dt>{{ __( 'Customer since' ) }}</dt>
                        <dd>{{ null === $customer->created_at ? '—' : LocalizedDate::format( $customer->created_at ) }}</dd>
                        <dt>{{ __( 'Marketing consent' ) }}</dt>
                        <dd data-customer-consent>
                            @if ( $customer->accepts_marketing && null !== $customer->accepts_marketing_at )
                                {{ __( 'Recorded :date', [ 'date' => LocalizedDate::format( $customer->accepts_marketing_at ) ] ) }}
                            @elseif ( $customer->accepts_marketing )
                                {{ __( 'Given' ) }}
                            @else
                                <span class="ec-muted">{{ __( 'Not given' ) }}</span>
                            @endif
                        </dd>
                    </dl>
                </x-artisanpack-card>
            @endif
        </div>

        <div class="ec-order-col" data-customer-column="main">
            @if ( [] !== $tabs )
                <x-artisanpack-card data-customer-tabs>
                    <x-artisanpack-tabs
                        variant="underline"
                        wire:model.live="tab"
                        :label="__( 'Customer details' )"
                        :classes="[ 'list' => 'ec-customer-tablist' ]"
                    >
                        @foreach ( $tabs as $customerTab )
                            <x-artisanpack-tab :name="$customerTab['key']" :label="$customerTab['label']" wire:key="customer-tab-{{ $customerTab['key'] }}">
                                <div class="ec-customer-tab" data-customer-tab="{{ $customerTab['key'] }}">
                                    @if ( $tab === $customerTab['key'] )
                                        @livewire( $customerTab['component'], [ $customerTab['parameter'] => $customer ], key( 'customer-tab-component-' . $customerTab['key'] ) )
                                    @endif
                                </div>
                            </x-artisanpack-tab>
                        @endforeach
                    </x-artisanpack-tabs>
                </x-artisanpack-card>
            @endif
        </div>
    </div>

    @if ( $confirmingDelete && $canDelete )
        <x-artisanpack-dialog
            wire:model="confirmingDelete"
            role="alertdialog"
            color="error"
            icon="trash"
            class="ec-dialog-wide"
            :title="__( 'Delete this customer?' )"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
            data-customer-delete
        >
            <div class="ec-stack">
                <div class="ec-customer-delete-columns">
                    <section aria-labelledby="customer-delete-erased">
                        <x-artisanpack-heading :level="3" :size="6" id="customer-delete-erased">{{ __( 'Erased' ) }}</x-artisanpack-heading>
                        <ul class="ec-customer-delete-list" data-tone="erased">
                            @foreach ( [
                                __( 'The customer record: name, email, and phone' ),
                                __( 'Saved addresses and notification preferences' ),
                                __( 'Internal notes about the customer' ),
                                __( 'On each order: the email, phone, addresses, order note, IP address, and browser' ),
                                __( 'The same details on guest orders, carts, and reviews placed with this email' ),
                                __( 'Personal details in the activity history, order notes, order edit history, and reasons staff wrote on refunds, edits, and cancellations' ),
                            ] as $erased )
                                <li><x-artisanpack-icon name="x" aria-hidden="true" /><span>{{ $erased }}</span></li>
                            @endforeach
                        </ul>
                    </section>
                    <section aria-labelledby="customer-delete-kept">
                        <x-artisanpack-heading :level="3" :size="6" id="customer-delete-kept">{{ __( 'Kept' ) }}</x-artisanpack-heading>
                        <ul class="ec-customer-delete-list" data-tone="kept">
                            @foreach ( [
                                __( 'Orders, their items, and every total, so sales and tax reports do not change' ),
                                __( 'Refunds, shipments, downloads, and license keys' ),
                                __( 'Reviews, shown as written by "Anonymous"' ),
                                __( 'The user account, if one is linked (only the link to it is removed)' ),
                                __( 'Copies held outside the store\'s records, such as the payment provider, sent emails, and server logs' ),
                            ] as $kept )
                                <li><x-artisanpack-icon name="check" aria-hidden="true" /><span>{{ $kept }}</span></li>
                            @endforeach
                        </ul>
                    </section>
                </div>

                <x-artisanpack-alert color="error" icon="triangle-alert" :title="__( 'This cannot be undone.' )" />

                <form wire:submit="deleteCustomer( {{ \Illuminate\Support\Js::from( $deleteToken ) }} )" id="customer-delete-form" data-customer-delete-form>
                    <x-artisanpack-input
                        id="customer-delete-confirmation"
                        class="ec-mono-input"
                        dir="ltr"
                        :label="__( 'Type :email to confirm', [ 'email' => $customer->email ] )"
                        autocomplete="off"
                        spellcheck="false"
                        wire:model.live.debounce.250ms="deleteConfirmation"
                        data-autofocus
                    />
                </form>
            </div>

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelDelete" data-focus-return="customer-delete">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                <x-artisanpack-button
                    type="submit"
                    form="customer-delete-form"
                    color="error"
                    :disabled="! $deleteMatches"
                    wire:loading.attr="disabled"
                    wire:target="deleteCustomer"
                    data-customer-delete-submit
                >{{ __( 'Delete customer' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
