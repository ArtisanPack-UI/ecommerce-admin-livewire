{{--
    Customer detail. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\Show.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
@endphp
<div class="flex flex-col gap-4">
    <x-artisanpack-header
        :title="$name ?? $customer->email"
        :subtitle="null === $name ? null : $customer->email"
        :level="1"
        separator
    >
        <x-slot:actions>
            @if ( null !== $indexUrl )
                <x-artisanpack-button variant="ghost" icon="o-arrow-left" :link="$indexUrl" :label="__( 'All customers' )" />
            @endif
            @if ( $canUpdate && ! $editing )
                <x-artisanpack-button icon="o-pencil" wire:click="startEdit" :label="__( 'Edit' )" data-edit-customer />
            @endif
            @if ( $canDelete && ! $confirmingDelete )
                <x-artisanpack-button variant="outline" color="error" icon="o-trash" wire:click="startDelete" :label="__( 'Delete' )" data-delete-customer />
            @endif
        </x-slot:actions>
    </x-artisanpack-header>

    <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __( 'Customer status' ) }}">
        <x-artisanpack-badge :value="null === $customer->user_id ? __( 'Guest' ) : __( 'Has an account' )" class="badge-sm" color="neutral" />
        <x-artisanpack-badge :value="$customer->accepts_marketing ? __( 'Subscribed to marketing' ) : __( 'Not subscribed to marketing' )" class="badge-sm" :color="$customer->accepts_marketing ? 'success' : 'neutral'" />
    </div>

    <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" data-customer-stats>
        <div class="rounded-box border border-base-content/10 p-3">
            <dt class="text-sm opacity-75">{{ __( 'Lifetime value' ) }}</dt>
            <dd class="text-lg font-semibold" data-stat="lifetime-value">{{ MoneyFormatter::format( $stats['lifetimeValue'], $stats['currency'] ) }}</dd>
        </div>
        <div class="rounded-box border border-base-content/10 p-3">
            <dt class="text-sm opacity-75">{{ __( 'Orders' ) }}</dt>
            <dd class="text-lg font-semibold tabular-nums" data-stat="order-count">{{ $stats['orderCount'] }}</dd>
        </div>
        <div class="rounded-box border border-base-content/10 p-3">
            <dt class="text-sm opacity-75">{{ __( 'Average order value' ) }}</dt>
            <dd class="text-lg font-semibold" data-stat="average-order-value">{{ null === $stats['averageOrderValue'] ? '—' : MoneyFormatter::format( $stats['averageOrderValue'], $stats['currency'] ) }}</dd>
        </div>
        <div class="rounded-box border border-base-content/10 p-3">
            <dt class="text-sm opacity-75">{{ __( 'First order' ) }}</dt>
            <dd class="text-lg font-semibold" data-stat="first-order">{{ null === $stats['firstOrderAt'] ? __( 'None yet' ) : LocalizedDate::format( $stats['firstOrderAt'] ) }}</dd>
        </div>
        <div class="rounded-box border border-base-content/10 p-3">
            <dt class="text-sm opacity-75">{{ __( 'Last order' ) }}</dt>
            <dd class="text-lg font-semibold" data-stat="last-order">{{ null === $stats['lastOrderAt'] ? __( 'None yet' ) : LocalizedDate::format( $stats['lastOrderAt'] ) }}</dd>
        </div>
    </dl>

    @if ( $editing )
        <x-artisanpack-card :title="__( 'Edit customer' )" shadow>
            <form wire:submit="saveDetails" class="grid gap-4 sm:grid-cols-2" data-customer-details-form>
                <x-artisanpack-input id="customer-first-name" :label="__( 'First name' )" autocomplete="off" wire:model="firstName" />
                <x-artisanpack-input id="customer-last-name" :label="__( 'Last name' )" autocomplete="off" wire:model="lastName" />
                <x-artisanpack-input id="customer-phone" type="tel" :label="__( 'Phone' )" autocomplete="off" wire:model="phone" />
                <div class="sm:col-span-2">
                    <x-artisanpack-toggle
                        id="customer-accepts-marketing"
                        :label="__( 'Accepts marketing' )"
                        :hint="null === $customer->accepts_marketing_at
                            ? __( 'Turning this on records today as the date of consent.' )
                            : __( 'Consent recorded :date. Turning this off clears it.', [ 'date' => LocalizedDate::format( $customer->accepts_marketing_at ) ] )"
                        wire:model="acceptsMarketing"
                    />
                </div>
                <div class="flex gap-2 sm:col-span-2">
                    <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" spinner="saveDetails" :label="__( 'Save' )" />
                    <x-artisanpack-button variant="ghost" wire:click="cancelEdit" :label="__( 'Cancel' )" />
                </div>
            </form>
        </x-artisanpack-card>
    @endif

    @if ( $confirmingDelete )
        <div
            role="alertdialog"
            aria-modal="false"
            aria-labelledby="customer-delete-title"
            aria-describedby="customer-delete-description"
            class="rounded-box border border-error bg-base-100 p-4"
            x-data
            x-init="$nextTick( () => $el.querySelector( 'input' )?.focus() )"
            data-customer-delete
        >
            <h2 id="customer-delete-title" class="text-lg font-semibold">{{ __( 'Delete this customer?' ) }}</h2>

            <div id="customer-delete-description" class="my-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <h3 class="font-semibold">{{ __( 'Erased' ) }}</h3>
                    <ul class="list-disc ps-5">
                        <li>{{ __( 'The customer record: name, email, and phone' ) }}</li>
                        <li>{{ __( 'Saved addresses and notification preferences' ) }}</li>
                        <li>{{ __( 'Internal notes about the customer' ) }}</li>
                        <li>{{ __( 'On each order: the email, phone, addresses, order note, IP address, and browser' ) }}</li>
                        <li>{{ __( 'The same details on guest orders, carts, and reviews placed with this email' ) }}</li>
                        <li>{{ __( 'Personal details in the activity history, order notes, order edit history, and reasons staff wrote on refunds, edits, and cancellations' ) }}</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold">{{ __( 'Kept' ) }}</h3>
                    <ul class="list-disc ps-5">
                        <li>{{ __( 'Orders, their items, and every total, so sales and tax reports do not change' ) }}</li>
                        <li>{{ __( 'Refunds, shipments, downloads, and license keys' ) }}</li>
                        <li>{{ __( 'Reviews, shown as written by "Anonymous"' ) }}</li>
                        <li>{{ __( 'The user account, if one is linked (only the link to it is removed)' ) }}</li>
                        <li>{{ __( 'Copies held outside the store\'s records, such as the payment provider, sent emails, and server logs' ) }}</li>
                    </ul>
                </div>
            </div>

            <p class="mb-3 font-semibold">{{ __( 'This cannot be undone.' ) }}</p>

            <form wire:submit="deleteCustomer( {{ \Illuminate\Support\Js::from( $deleteToken ) }} )" class="flex flex-wrap items-end gap-2">
                <x-artisanpack-input
                    id="customer-delete-confirmation"
                    class="min-w-72"
                    :label="__( 'Type :email to confirm', [ 'email' => $customer->email ] )"
                    autocomplete="off"
                    wire:model="deleteConfirmation"
                />
                <x-artisanpack-button type="submit" color="error" wire:loading.attr="disabled" spinner="deleteCustomer" :label="__( 'Delete customer' )" />
                <x-artisanpack-button variant="ghost" wire:click="cancelDelete" :label="__( 'Cancel' )" />
            </form>
        </div>
    @endif

    @if ( [] !== $tabs )
        <x-artisanpack-tabs wire:model.live="tab" aria-label="{{ __( 'Customer details' ) }}">
            @foreach ( $tabs as $customerTab )
                <x-artisanpack-tab :name="$customerTab['key']" :label="$customerTab['label']" wire:key="customer-tab-{{ $customerTab['key'] }}">
                    <div class="pt-4" data-customer-tab="{{ $customerTab['key'] }}">
                        @if ( $tab === $customerTab['key'] )
                            @livewire( $customerTab['component'], [ $customerTab['parameter'] => $customer ], key( 'customer-tab-component-' . $customerTab['key'] ) )
                        @endif
                    </div>
                </x-artisanpack-tab>
            @endforeach
        </x-artisanpack-tabs>
    @endif
</div>
