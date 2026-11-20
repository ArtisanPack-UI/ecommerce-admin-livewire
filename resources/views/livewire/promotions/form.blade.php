{{--
    Promotion form (#131). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\Form.

    Create or edit a promotion. Under the page header (Save is its one solid
    button), a badge row shows the promotion's state (#92) and source. One
    card holds underline tabs: Details, Rules (the shared Rule builder, #88),
    and once saved Coupons (#111, coupon promotions only), Usage (#112) and
    Activity.

    Details is four sections (Basics, Schedule, Limits, and Stacking and
    priority), each with its heading on the start side and its fields on
    the end side; the stacking explanation sits beside the two fields it
    governs. On narrow screens the heading stacks above the fields. Source
    is a two-option segmented control whose icons match the list's source
    badges.

    After a failed save the error summary (#90) leads, and each tab with
    errors carries an error marker. The marker is the tab's count part: it
    holds "(has errors)", so the tab's name says it too, and admin.css draws
    it as a red badge.

    View only: every control is disabled, Save is hidden, and an info alert
    says so.

    Unsaved changes: typing in Details or Rules marks the form dirty (the
    Coupons, Usage and Activity tabs are separate components and don't);
    leaving the page (or a wire:navigate link) asks first until the form is
    saved.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\Form;
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\Index;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\UnsavedChanges;

    $tabError       = static fn ( string $key ): ?string => in_array( $key, $errorTabs, true ) ? __( '(has errors)' ) : null;
    $sourceError    = $errors->first( 'sourceType' );
    $sourceDescribe = 'promotion-source-hint' . ( '' !== $sourceError ? ' promotion-source-error' : '' );
@endphp
{{-- Only the Details and Rules fields (data-unsaved-scope) mark the form dirty: the Coupons, Usage and Activity tabs are their own components that save (or search) on their own. --}}
<div
    class="ec-stack"
    data-promotion-form
    x-data="{ {!! UnsavedChanges::members() !!} }"
    data-unsaved-changes
    x-on:input="if ( $event.target.closest( '[data-unsaved-scope]' ) ) dirty = true"
    x-on:change="if ( $event.target.closest( '[data-unsaved-scope]' ) ) dirty = true"
    x-on:beforeunload.window="warnBeforeUnload( $event )"
    x-on:livewire:navigate.document="confirmNavigate( $event )"
    x-on:{{ UnsavedChanges::SAVED_EVENT }}.window="dirty = false"
>
    <x-artisanpack-page-header
        :title="$isCreate ? __( 'New promotion' ) : __( 'Edit :name', [ 'name' => $promotion->name ] )"
        :level="1"
    >
        <x-slot:actions>
            @if ( null !== $indexUrl )
                <x-artisanpack-button variant="ghost" color="neutral" size="sm" :href="$indexUrl" data-back-link>{{ __( 'All promotions' ) }}</x-artisanpack-button>
            @endif
            @unless ( $readOnly )
                <x-artisanpack-button size="sm" leading-icon="check" wire:click="save" wire:loading.attr="disabled" wire:target="save" data-save>{{ __( 'Save' ) }}</x-artisanpack-button>
            @endunless
        </x-slot:actions>
    </x-artisanpack-page-header>

    @if ( null !== $promotion )
        <div class="ec-badge-row ec-promotion-form-badges" role="group" aria-label="{{ __( 'Promotion status' ) }}" data-promotion-badges>
            @include( 'ecommerce-admin::livewire.promotions.cells.state', [ 'row' => $promotion ] )
            <x-artisanpack-badge variant="soft" color="neutral" :leading-icon="Form::sourceIcon( (string) $promotion->source_type )" data-promotion-source="{{ $promotion->source_type }}">
                {{ Index::sourceLabel( (string) $promotion->source_type ) }}
            </x-artisanpack-badge>
        </div>
    @endif

    @if ( $readOnly )
        <x-artisanpack-alert color="info" icon="lock" :title="__( 'You can view this promotion but not change it.' )" data-read-only />
    @endif

    @include( 'ecommerce-admin::partials.error-summary' )

    <x-artisanpack-card :classes="[ 'body' => 'ec-stack' ]">
        <x-artisanpack-tabs
            variant="underline"
            wire:model="tab"
            :label="__( 'Promotion details' )"
            class="ec-promotion-form-tabs"
            :classes="[ 'list' => 'ec-promotion-form-tablist' ]"
        >
            {{-- Details --}}
            <x-artisanpack-tab value="details" :label="$tabLabels['details']" :count="$tabError( 'details' )">
                <fieldset class="ec-promotion-form-panel" @disabled( $readOnly ) data-unsaved-scope data-promotion-details>
                    <section class="ec-promotion-form-section" aria-labelledby="promotion-basics-title">
                        <div class="ec-promotion-form-section__head">
                            <x-artisanpack-heading :level="2" :size="6" id="promotion-basics-title">{{ __( 'Basics' ) }}</x-artisanpack-heading>
                        </div>
                        <div class="ec-field-grid">
                            <x-artisanpack-input id="promotion-name" class="ec-span-2" :label="__( 'Name' )" wire:model.live.blur="name" :disabled="$readOnly" required />
                            <x-artisanpack-input
                                id="promotion-key"
                                class="ec-span-2 ec-mono-input"
                                dir="ltr"
                                :label="__( 'Key' )"
                                :hint="__( 'A unique, permanent identifier, like black-friday-2026. Filled from the name until you change it.' )"
                                wire:model.live.blur="key"
                                :disabled="$readOnly"
                                required
                            />
                            <x-artisanpack-textarea
                                id="promotion-description"
                                class="ec-span-2"
                                :label="__( 'Description' )"
                                :hint="__( 'For staff only; customers never see it.' )"
                                rows="3"
                                wire:model="description"
                                :disabled="$readOnly"
                            />

                            {{-- A segmented radio group drawn here so each option can lead with its source icon. --}}
                            <fieldset
                                class="apui-radio-group ec-span-2 ec-promotion-source"
                                id="promotion-source"
                                data-orientation="horizontal"
                                data-variant="segmented"
                                aria-describedby="{{ $sourceDescribe }}"
                                @if ( '' !== $sourceError ) data-invalid @endif
                                @if ( $readOnly ) data-disabled disabled @endif
                                data-promotion-source-control
                            >
                                <legend data-scope="radio-group" data-part="label">{{ __( 'Source' ) }}</legend>
                                <div data-scope="radio-group" data-part="items">
                                    @foreach ( $sourceOptions as $index => $option )
                                        <label data-scope="radio-group" data-part="item" wire:key="promotion-source-{{ $option['id'] }}" @if ( $readOnly ) data-disabled @endif>
                                            <span data-scope="radio-group" data-part="box">
                                                <input
                                                    type="radio"
                                                    data-scope="radio-group"
                                                    data-part="control"
                                                    id="promotion-source-{{ $index }}"
                                                    name="promotion-source"
                                                    value="{{ $option['id'] }}"
                                                    wire:model.live="sourceType"
                                                    @checked( $sourceType === $option['id'] )
                                                    @disabled( $readOnly )
                                                    @if ( '' !== $sourceError ) aria-invalid="true" @endif
                                                />
                                                <span data-scope="radio-group" data-part="indicator" aria-hidden="true"></span>
                                            </span>
                                            <span data-scope="radio-group" data-part="item-text">
                                                @if ( null !== $option['icon'] )
                                                    <x-artisanpack-icon :name="$option['icon']" />
                                                @endif
                                                {{ $option['name'] }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <p data-scope="radio-group" data-part="hint" id="promotion-source-hint">{{ __( 'Automatic promotions apply on their own. Coupon promotions apply when the customer enters one of their codes.' ) }}</p>
                                @if ( '' !== $sourceError )
                                    <p data-scope="radio-group" data-part="error" id="promotion-source-error"><x-artisanpack-icon name="circle-alert" />{{ $sourceError }}</p>
                                @endif
                            </fieldset>
                        </div>
                    </section>

                    <section class="ec-promotion-form-section" aria-labelledby="promotion-schedule-title">
                        <div class="ec-promotion-form-section__head">
                            <x-artisanpack-heading :level="2" :size="6" id="promotion-schedule-title">{{ __( 'Schedule' ) }}</x-artisanpack-heading>
                        </div>
                        <div class="ec-field-grid">
                            <x-artisanpack-toggle
                                id="promotion-active"
                                class="ec-span-2"
                                :label="__( 'Switched on' )"
                                :description="__( 'Switch off to stop the promotion now, whatever its dates say.' )"
                                wire:model="isActive"
                                :disabled="$readOnly"
                            />
                            <x-artisanpack-input
                                id="promotion-starts-at"
                                type="datetime-local"
                                dir="ltr"
                                leading-icon="calendar"
                                :label="__( 'Starts' )"
                                :hint="__( 'Leave empty to start as soon as it is switched on. Times are in :zone.', [ 'zone' => $timezone ] )"
                                wire:model="startsAt"
                                :disabled="$readOnly"
                            />
                            <x-artisanpack-input
                                id="promotion-ends-at"
                                type="datetime-local"
                                dir="ltr"
                                leading-icon="calendar"
                                :label="__( 'Ends' )"
                                :hint="__( 'Leave empty to run until switched off.' )"
                                wire:model="endsAt"
                                :disabled="$readOnly"
                            />
                        </div>
                    </section>

                    <section class="ec-promotion-form-section" aria-labelledby="promotion-limits-title">
                        <div class="ec-promotion-form-section__head">
                            <x-artisanpack-heading :level="2" :size="6" id="promotion-limits-title">{{ __( 'Limits' ) }}</x-artisanpack-heading>
                        </div>
                        <div class="ec-field-grid">
                            <x-artisanpack-input
                                id="promotion-usage-limit-total"
                                type="number"
                                min="1"
                                step="1"
                                inputmode="numeric"
                                dir="ltr"
                                :label="__( 'Total use limit' )"
                                :hint="__( 'How many orders can use it in all. Leave empty for no limit.' )"
                                wire:model="usageLimitTotal"
                                :disabled="$readOnly"
                            />
                            <x-artisanpack-input
                                id="promotion-usage-limit-per-customer"
                                type="number"
                                min="1"
                                step="1"
                                inputmode="numeric"
                                dir="ltr"
                                :label="__( 'Use limit per customer' )"
                                :hint="__( 'Leave empty for no limit. Guests are limited only by the total.' )"
                                wire:model="usageLimitPerCustomer"
                                :disabled="$readOnly"
                            />
                        </div>
                    </section>

                    <section class="ec-promotion-form-section" aria-labelledby="promotion-stacking-title" aria-describedby="promotion-stacking-description" data-promotion-stacking>
                        <div class="ec-promotion-form-section__head">
                            <x-artisanpack-heading :level="2" :size="6" id="promotion-stacking-title">{{ __( 'Stacking and priority' ) }}</x-artisanpack-heading>
                            <p class="ec-muted" id="promotion-stacking-description">
                                {{ __( 'When several promotions qualify, they apply in priority order, lowest number first, each to whatever the earlier ones left. An exclusive promotion applies only if nothing has applied before it, and once it applies, nothing after it does. So give an exclusive promotion a low number to make it win, or a high one to use it only when nothing else qualifies.' ) }}
                            </p>
                        </div>
                        <div class="ec-field-grid">
                            <x-artisanpack-input
                                id="promotion-priority"
                                type="number"
                                step="1"
                                inputmode="numeric"
                                dir="ltr"
                                :label="__( 'Priority' )"
                                :hint="__( 'Lower numbers apply first. Ties apply in the order the promotions were created.' )"
                                wire:model="priority"
                                :disabled="$readOnly"
                            />
                            <x-artisanpack-toggle
                                id="promotion-exclusive"
                                class="ec-promotion-form-centered"
                                :label="__( 'Exclusive' )"
                                :description="__( 'Do not combine with other promotions.' )"
                                wire:model="isExclusive"
                                :disabled="$readOnly"
                            />
                        </div>
                    </section>
                </fieldset>
            </x-artisanpack-tab>

            {{-- Rules --}}
            <x-artisanpack-tab value="rules" :label="$tabLabels['rules']" :count="$tabError( 'rules' )">
                <fieldset class="ec-promotion-form-panel" @disabled( $readOnly ) data-unsaved-scope data-promotion-rules>
                    <x-artisanpack-ec-rule-builder :builder="$ruleBuilder" prefix="promotion-rule" />
                </fieldset>
            </x-artisanpack-tab>

            @if ( in_array( 'coupons', $tabs, true ) )
                <x-artisanpack-tab value="coupons" :label="$tabLabels['coupons']">
                    @livewire( 'artisanpack-ecommerce-admin-promotion-coupons', [ 'promotion' => $promotion ], key( 'promotion-coupons-' . $promotion->id ) )
                </x-artisanpack-tab>
            @endif

            @if ( in_array( 'usage', $tabs, true ) )
                <x-artisanpack-tab value="usage" :label="$tabLabels['usage']">
                    @livewire( 'artisanpack-ecommerce-admin-promotion-usage', [ 'promotion' => $promotion ], key( 'promotion-usage-' . $promotion->id ) )
                </x-artisanpack-tab>
            @endif

            @if ( in_array( 'activity', $tabs, true ) )
                <x-artisanpack-tab value="activity" :label="$tabLabels['activity']">
                    @livewire( 'artisanpack-ecommerce-admin-timeline', [ 'subject' => $promotion ], key( 'promotion-timeline-' . $promotion->id ) )
                </x-artisanpack-tab>
            @endif
        </x-artisanpack-tabs>
    </x-artisanpack-card>
</div>
