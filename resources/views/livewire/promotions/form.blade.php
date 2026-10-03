{{--
    Promotion form. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\Form.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\Index;
@endphp
<div class="flex flex-col gap-4" data-promotion-form>
    <x-artisanpack-header
        :title="$isCreate ? __( 'New promotion' ) : __( 'Edit :name', [ 'name' => $promotion->name ] )"
        :level="1"
        separator
    >
        <x-slot:actions>
            @if ( null !== $indexUrl )
                <x-artisanpack-button variant="ghost" icon="o-arrow-left" :link="$indexUrl" :label="__( 'All promotions' )" />
            @endif
            @unless ( $readOnly )
                <x-artisanpack-button color="primary" icon="o-check" wire:click="save" wire:loading.attr="disabled" spinner="save" :label="__( 'Save' )" data-save />
            @endunless
        </x-slot:actions>
    </x-artisanpack-header>

    @if ( null !== $state )
        <div class="flex flex-wrap items-center gap-2">
            @include( 'ecommerce-admin::livewire.promotions.cells.state', [ 'row' => $promotion ] )
            <span class="text-sm opacity-75">{{ Index::windowLabel( $promotion ) }} &middot; {{ Index::usesLabel( $promotion ) }}</span>
        </div>
    @endif

    @if ( $readOnly )
        <x-artisanpack-alert color="info" icon="o-eye" :title="__( 'You can view this promotion but not change it.' )" role="status" />
    @endif

    @if ( $errors->any() )
        <x-artisanpack-alert color="error" icon="o-exclamation-circle" role="alert" :title="__( 'The promotion was not saved. Fix the highlighted fields.' )" />
    @endif

    <x-artisanpack-tabs wire:model="tab" aria-label="{{ __( 'Promotion details' ) }}">
        <x-artisanpack-tab name="details" :label="__( 'Details' )" icon="o-document-text">
            <fieldset class="grid gap-4 pt-4 md:grid-cols-2" @disabled( $readOnly ) data-promotion-details>
                <x-artisanpack-input id="promotion-name" :label="__( 'Name' )" wire:model.blur="name" required />
                <x-artisanpack-input
                    id="promotion-key"
                    :label="__( 'Key' )"
                    :hint="__( 'A unique, permanent identifier, like black-friday-2026. Filled from the name until you change it.' )"
                    wire:model.blur="key"
                    required
                />

                <div class="md:col-span-2">
                    <x-artisanpack-textarea id="promotion-description" :label="__( 'Description' )" :hint="__( 'For staff only; customers never see it.' )" rows="2" wire:model="description" />
                </div>

                <x-artisanpack-select
                    id="promotion-source"
                    :label="__( 'Source' )"
                    :hint="__( 'Automatic promotions apply on their own. Coupon promotions apply when the customer enters one of their codes.' )"
                    :options="$sourceOptions"
                    wire:model.live="sourceType"
                />

                <div class="flex items-end">
                    <x-artisanpack-toggle id="promotion-active" :label="__( 'Switched on' )" :hint="__( 'Switch off to stop the promotion now, whatever its dates say.' )" wire:model="isActive" />
                </div>

                <x-artisanpack-input
                    id="promotion-starts-at"
                    type="datetime-local"
                    :label="__( 'Starts' )"
                    :hint="__( 'Leave empty to start as soon as it is switched on. Times are in :zone.', [ 'zone' => $timezone ] )"
                    wire:model="startsAt"
                />
                <x-artisanpack-input
                    id="promotion-ends-at"
                    type="datetime-local"
                    :label="__( 'Ends' )"
                    :hint="__( 'Leave empty to run until switched off.' )"
                    wire:model="endsAt"
                />

                <x-artisanpack-input
                    id="promotion-usage-limit-total"
                    type="number"
                    min="1"
                    step="1"
                    :label="__( 'Total use limit' )"
                    :hint="__( 'How many orders can use it in all. Leave empty for no limit.' )"
                    wire:model="usageLimitTotal"
                />
                <x-artisanpack-input
                    id="promotion-usage-limit-per-customer"
                    type="number"
                    min="1"
                    step="1"
                    :label="__( 'Use limit per customer' )"
                    :hint="__( 'Leave empty for no limit. Guests are limited only by the total.' )"
                    wire:model="usageLimitPerCustomer"
                />

                <section class="rounded-box border border-base-content/10 p-4 md:col-span-2" aria-labelledby="promotion-stacking-title" data-promotion-stacking>
                    <h2 id="promotion-stacking-title" class="mb-1 font-semibold">{{ __( 'Stacking and priority' ) }}</h2>
                    <p class="mb-3 text-sm opacity-75">
                        {{ __( 'When several promotions qualify, they apply in priority order, lowest number first, each to whatever the earlier ones left. An exclusive promotion applies only if nothing has applied before it, and once it applies, nothing after it does. So give an exclusive promotion a low number to make it win, or a high one to use it only when nothing else qualifies.' ) }}
                    </p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <x-artisanpack-input
                            id="promotion-priority"
                            type="number"
                            step="1"
                            :label="__( 'Priority' )"
                            :hint="__( 'Lower numbers apply first. Ties apply in the order the promotions were created.' )"
                            wire:model="priority"
                        />
                        <div class="flex items-end">
                            <x-artisanpack-toggle id="promotion-exclusive" :label="__( 'Exclusive' )" :hint="__( 'Do not combine with other promotions.' )" wire:model="isExclusive" />
                        </div>
                    </div>
                </section>
            </fieldset>
        </x-artisanpack-tab>

        <x-artisanpack-tab name="rules" :label="$errors->has( 'ruleRows.*' ) ? __( 'Rules (has errors)' ) : __( 'Rules' )" icon="o-adjustments-horizontal">
            <fieldset class="pt-4" @disabled( $readOnly )>
                <x-artisanpack-ec-rule-builder :builder="$ruleBuilder" />
            </fieldset>
        </x-artisanpack-tab>

        @if ( in_array( 'coupons', $tabs, true ) )
            <x-artisanpack-tab name="coupons" :label="__( 'Coupons' )" icon="o-ticket">
                <div class="pt-4">
                    @livewire( 'artisanpack-ecommerce-admin-promotion-coupons', [ 'promotion' => $promotion ], key( 'promotion-coupons-' . $promotion->id ) )
                </div>
            </x-artisanpack-tab>
        @endif

        @if ( in_array( 'usage', $tabs, true ) )
            <x-artisanpack-tab name="usage" :label="__( 'Usage' )" icon="o-chart-bar">
                <div class="pt-4">
                    @livewire( 'artisanpack-ecommerce-admin-promotion-usage', [ 'promotion' => $promotion ], key( 'promotion-usage-' . $promotion->id ) )
                </div>
            </x-artisanpack-tab>
        @endif

        @if ( in_array( 'activity', $tabs, true ) )
            <x-artisanpack-tab name="activity" :label="__( 'Activity' )" icon="o-clock">
                <div class="pt-4">
                    @livewire( 'artisanpack-ecommerce-admin-timeline', [ 'subject' => $promotion ], key( 'promotion-timeline-' . $promotion->id ) )
                </div>
            </x-artisanpack-tab>
        @endif
    </x-artisanpack-tabs>
</div>
