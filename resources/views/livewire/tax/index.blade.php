{{--
    Tax (#133). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Tax\Index.

    An info alert names the provider that calculates tax; when it isn't the
    built-in rates, a warning says plainly the rates on this page aren't used.
    Tax classes sit in a card with what uses each one, so you know before
    deleting, and are relabelled in their row. The rates are the shared
    Resource table (#79) inside a second card, edited one row at a time
    (cells/rate). A class in use can't be deleted: its dialog only offers
    Keep class. Import runs a dry run first, then names how many rates it
    loads.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Tax\Index;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use Illuminate\Support\Js;
@endphp
<div class="ec-stack" data-tax-index>
    <x-artisanpack-page-header :title="__( 'Tax' )" :description="__( 'Tax classes and the rates checkout charges.' )" :level="1" />

    <div data-tax-provider="{{ $provider['key'] }}">
        @if ( $provider['manual'] )
            <x-artisanpack-alert
                color="info"
                icon="info"
                :title="__( 'Taxes are calculated by :provider.', [ 'provider' => $provider['label'] ] ) . ' ' . __( 'Checkout uses the active rates below.' )"
            />
        @else
            <x-artisanpack-alert
                color="warning"
                icon="triangle-alert"
                :title="__( 'These rates are not used.' )"
                :description="__( 'Taxes are calculated by :provider, so checkout ignores the rates on this page. Keep them as a fallback, or switch the provider in Settings.', [ 'provider' => $provider['label'] ] )"
                data-rates-unused
            />
        @endif
    </div>

    <section class="apui-card" aria-labelledby="tax-classes-heading" data-tax-classes>
        <div data-scope="card" data-part="header">
            <div>
                <h2 id="tax-classes-heading" data-scope="card" data-part="title">{{ __( 'Tax classes' ) }}</h2>
                <p data-scope="card" data-part="description">{{ __( 'Products and shipping methods choose a class; each rate applies to one class.' ) }}</p>
            </div>
        </div>

        <div data-scope="card" data-part="body" class="ec-stack">
            <div class="apui-table ec-table">
                <div data-scope="table" data-part="scroll">
                    <table data-scope="table" data-part="table">
                        <caption class="apui-sr-only">{{ __( 'Tax classes' ) }}</caption>
                        <thead data-scope="table" data-part="head">
                            <tr data-scope="table" data-part="row">
                                <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Class' ) }}</th>
                                <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Key' ) }}</th>
                                <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Used by' ) }}</th>
                                <th data-scope="table" data-part="head-cell" scope="col" data-numeric><span class="apui-sr-only">{{ __( 'Actions' ) }}</span></th>
                            </tr>
                        </thead>
                        <tbody data-scope="table" data-part="body">
                            @foreach ( $classes as $class )
                                @php
                                    $renaming  = $editingClassId === (int) $class->id;
                                    $classFocus = 'tax-class-' . $class->id;
                                @endphp
                                <tr data-scope="table" data-part="row" wire:key="tax-class-{{ $class->id }}" data-tax-class="{{ $class->key }}">
                                    <td data-scope="table" data-part="cell">
                                        @if ( $renaming )
                                            <form
                                                wire:submit="saveClass"
                                                class="ec-tax-rename"
                                                data-edit-class="{{ $class->key }}"
                                                x-data
                                                x-init="$nextTick( () => $el.querySelector( 'input' )?.focus() )"
                                                x-on:keydown.escape.prevent.stop="$el.querySelector( '[data-rename-cancel]' )?.click()"
                                            >
                                                <div class="ec-tax-rename-field">
                                                    <x-artisanpack-input id="tax-class-{{ $class->id }}-label" size="sm" :aria-label="__( 'Label' )" maxlength="120" wire:model="editingClassLabel" />
                                                </div>
                                                <x-artisanpack-button type="submit" size="xs" wire:loading.attr="disabled" wire:target="saveClass" data-focus-return="{{ $classFocus }}">{{ __( 'Save' ) }}</x-artisanpack-button>
                                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" wire:click="cancelClass" data-rename-cancel data-focus-return="{{ $classFocus }}">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                                            </form>
                                        @else
                                            {{ $class->label }}
                                        @endif
                                    </td>
                                    <td data-scope="table" data-part="cell"><bdi dir="ltr" class="ec-mono">{{ $class->key }}</bdi></td>
                                    <td data-scope="table" data-part="cell"><span class="ec-muted" data-class-usage>{{ Index::usageSummary( $classUsage[ $class->key ] ) }}</span></td>
                                    <td data-scope="table" data-part="cell" data-numeric>
                                        @unless ( $renaming )
                                            <div class="ec-row-actions ec-row-actions-end">
                                                @if ( $canUpdate )
                                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="pencil" wire:click="editClass( {{ $class->id }} )" data-focus-key="{{ $classFocus }}" :aria-label="__( 'Edit :name', [ 'name' => $class->label ] )" />
                                                @endif
                                                @if ( $canDelete )
                                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="confirmDeleteClass( {{ $class->id }} )" data-focus-key="tax-class-delete-{{ $class->id }}" :aria-label="__( 'Delete :name', [ 'name' => $class->label ] )" />
                                                @endif
                                            </div>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if ( $canCreate )
                <form wire:submit="createClass" class="ec-sunken ec-tax-add" aria-labelledby="tax-add-class-heading" data-new-class>
                    <h3 id="tax-add-class-heading" class="ec-subheading">{{ __( 'Add a class' ) }}</h3>
                    <div class="ec-tax-add-fields">
                        <x-artisanpack-input id="new-tax-class-key" class="ec-mono-input" dir="ltr" :label="__( 'Key' )" placeholder="reduced-rate" :hint="__( 'Lowercase, like reduced-rate. It cannot be changed later.' )" wire:model="newClassKey" maxlength="60" />
                        <x-artisanpack-input id="new-tax-class-label" :label="__( 'Label' )" :placeholder="__( 'Reduced rate' )" wire:model="newClassLabel" maxlength="120" />
                        <div class="ec-tax-add-submit">
                            <x-artisanpack-button type="submit" variant="outline" color="neutral" leading-icon="plus" wire:loading.attr="disabled" wire:target="createClass">{{ __( 'Add class' ) }}</x-artisanpack-button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </section>

    <section class="apui-card ec-tax-rates" aria-labelledby="tax-rates-heading" data-tax-rates>
        <div data-scope="card" data-part="header">
            <div>
                <h2 id="tax-rates-heading" data-scope="card" data-part="title">{{ __( 'Tax rates' ) }}</h2>
            </div>
            <div data-scope="card" data-part="actions">
                @if ( $hasRates )
                    <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="download" wire:click="exportRates" data-download-rates>{{ __( 'Download rates CSV' ) }}</x-artisanpack-button>
                @endif
                @if ( $canCreate )
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="file-down" wire:click="openImport" data-focus-key="tax-import">{{ __( 'Import CSV' ) }}</x-artisanpack-button>
                    <x-artisanpack-button size="sm" leading-icon="plus" wire:click="createRate" data-focus-key="tax-new-rate">{{ __( 'New rate' ) }}</x-artisanpack-button>
                @endif
            </div>
        </div>

        @if ( $creatingRate )
            <div data-scope="card" data-part="body">
                <form
                    wire:submit="saveRate"
                    class="ec-sunken ec-stack"
                    aria-labelledby="tax-new-rate-heading"
                    data-new-rate
                    x-data
                    x-init="$nextTick( () => $el.querySelector( 'select, input, button[role=combobox]' )?.focus() )"
                >
                    <h3 id="tax-new-rate-heading" class="ec-subheading">{{ __( 'New rate' ) }}</h3>
                    <div class="ec-field-grid ec-field-grid-3">
                        <x-artisanpack-select placeholder="" id="new-rate-class" :label="__( 'Tax class' )" :options="ControlOptions::from( $classOptions )" wire:model="rateForm.tax_class_key" />
                        <x-artisanpack-select id="new-rate-country" :label="__( 'Country' )" :options="ControlOptions::from( $countryOptions )" :placeholder="__( 'Choose a country' )" wire:model="rateForm.country_code" />
                        <x-artisanpack-input id="new-rate-region" class="ec-mono-input" dir="ltr" :label="__( 'Region' )" :hint="__( 'A state or province code, like NY. Leave empty for the whole country.' )" maxlength="10" wire:model="rateForm.region_code" />
                        <x-artisanpack-input id="new-rate-postal" class="ec-mono-input" dir="ltr" :label="__( 'Postal codes' )" :hint="__( 'Comma-separated; * matches anything and 10001...10099 is a range. Leave empty for all.' )" maxlength="60" wire:model="rateForm.postal_pattern" />
                        <x-artisanpack-ec-percent-input id="new-rate-rate" :label="__( 'Rate' )" wire:model="rateForm.rate_ubps" />
                        <x-artisanpack-input id="new-rate-label" :label="__( 'Label' )" :hint="__( 'Shown to shoppers, like Sales tax or VAT.' )" maxlength="120" wire:model="rateForm.label" />
                        <x-artisanpack-input id="new-rate-priority" type="number" step="1" dir="ltr" :label="__( 'Priority' )" :hint="__( 'Rates with different priorities for the same place stack.' )" wire:model="rateForm.priority" />
                    </div>
                    <div class="ec-tax-choices">
                        <x-artisanpack-checkbox id="new-rate-compound" :label="__( 'Compound' )" :description="__( 'Charged on top of the other taxes.' )" wire:model="rateForm.is_compound" />
                        <x-artisanpack-checkbox id="new-rate-shipping" :label="__( 'Taxes shipping' )" wire:model="rateForm.is_shipping_taxable" />
                        <x-artisanpack-checkbox id="new-rate-active" :label="__( 'Active' )" wire:model="rateForm.is_active" />
                    </div>
                    <div class="ec-row-actions ec-tax-form-actions">
                        <x-artisanpack-button type="submit" wire:loading.attr="disabled" wire:target="saveRate" data-focus-return="tax-new-rate">{{ __( 'Save rate' ) }}</x-artisanpack-button>
                        <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelRate" data-focus-return="tax-new-rate">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                    </div>
                </form>
            </div>
        @endif

        <div data-scope="card" data-part="body" class="ec-tax-rates-table">
            @include( 'ecommerce-admin::partials.resource-table', [
                'emptyIcon'        => 'percent',
                'emptyTitle'       => __( 'No tax rates yet' ),
                'emptyDescription' => __( 'Add a rate for each place you collect tax, or import them from a CSV.' ),
                'rowLabel'         => static fn ( \ArtisanPackUI\Ecommerce\Models\TaxRate $rate ): string => $rate->label . ' (' . $rate->country_code . ')',
                'cellContext'      => [
                    'editingRateId'  => $editingRateId,
                    'canUpdate'      => $canUpdate,
                    'canDelete'      => $canDelete,
                    'classOptions'   => $classOptions,
                    'countryOptions' => $countryOptions,
                ],
            ] )
        </div>
    </section>

    @if ( null !== $deletingClass )
        @php $deleteClassTitle = __( 'Delete ":name"?', [ 'name' => $deletingClass->label ] ); @endphp
        <x-artisanpack-dialog
            wire:model="confirmingClassDelete"
            role="alertdialog"
            size="sm"
            :color="null === $deletingBlocker ? 'error' : 'warning'"
            icon="trash"
            :title="$deleteClassTitle"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
            data-delete-class-dialog
        >
            <div data-delete-class="{{ $deletingClass->key }}">
                @if ( null !== $deletingBlocker )
                    <x-artisanpack-alert color="warning" icon="triangle-alert" :title="__( 'This class cannot be deleted yet.' )" :description="$deletingBlocker" data-class-blocked />
                @else
                    <p class="ec-body">{{ __( 'Nothing uses this class. Deleting it cannot be undone.' ) }}</p>
                @endif
            </div>

            <x-slot:footer>
                @if ( null !== $deletingBlocker )
                    <x-artisanpack-button color="neutral" wire:click="cancelDeleteClass" data-autofocus data-focus-return="tax-class-delete-{{ $deletingClass->id }}">{{ __( 'Keep class' ) }}</x-artisanpack-button>
                @else
                    <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelDeleteClass" data-autofocus data-focus-return="tax-class-delete-{{ $deletingClass->id }}">{{ __( 'Keep class' ) }}</x-artisanpack-button>
                    <x-artisanpack-button
                        color="error"
                        wire:click="deleteClass( {{ Js::from( $deleteClassToken ) }} )"
                        wire:loading.attr="disabled"
                        data-delete-class-button>{{ __( 'Delete class' ) }}</x-artisanpack-button>
                @endif
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif

    @if ( $importing )
        @php
            $importCount = null === $importReport ? 0 : $importReport['create'] + $importReport['update'];
        @endphp
        <x-artisanpack-dialog
            wire:model="importing"
            icon="file-down"
            color="neutral"
            class="ec-dialog-wide"
            :title="__( 'Import tax rates' )"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
        >
            <div class="ec-stack" data-rates-import>
                <div class="ec-tax-import-file">
                    <x-artisanpack-file
                        id="tax-rates-file"
                        :title="__( 'Drop a CSV here or choose a file' )"
                        :hint="trans_choice( 'At most :count row.|At most :count rows.', $maxRows, [ 'count' => $maxRows ] )"
                        accept=".csv,text/csv,text/plain"
                        wire:model="ratesCsv"
                    />
                    <div>
                        <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="download" wire:click="downloadSample">{{ __( 'Download a sample' ) }}</x-artisanpack-button>
                    </div>
                </div>

                <p class="ec-muted">{{ __( 'Columns:' ) }} <bdi dir="ltr" class="ec-mono">{{ implode( ', ', $csvColumns ) }}</bdi></p>
                <p class="ec-muted">{{ __( 'Only tax_class_key, country_code, rate_percent and label are required. A row with the same class, country, region, postal codes and priority as an existing rate updates it; any other row adds a rate.' ) }}</p>

                @if ( null !== $importReport )
                    <div class="ec-stack" data-import-report>
                        <x-artisanpack-alert
                            :color="$importCount > 0 ? 'success' : 'warning'"
                            :icon="$importCount > 0 ? 'circle-check' : 'triangle-alert'"
                            :title="__( ':create to add, :update to update.', [ 'create' => $importReport['create'], 'update' => $importReport['update'] ] )"
                            :description="$importReport['invalid'] > 0 ? trans_choice( ':count row has errors and will be skipped.|:count rows have errors and will be skipped.', $importReport['invalid'], [ 'count' => $importReport['invalid'] ] ) : null"
                            data-import-counts
                        />

                        @if ( [] !== $importReport['rows'] )
                            <div class="apui-table ec-table" data-density="compact">
                                <div data-scope="table" data-part="scroll">
                                    <table data-scope="table" data-part="table">
                                        <caption class="apui-sr-only">{{ __( 'Rows with errors' ) }}</caption>
                                        <thead data-scope="table" data-part="head">
                                            <tr data-scope="table" data-part="row">
                                                <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Row' ) }}</th>
                                                <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Problem' ) }}</th>
                                            </tr>
                                        </thead>
                                        <tbody data-scope="table" data-part="body">
                                            @foreach ( $importReport['rows'] as $reportRow )
                                                <tr data-scope="table" data-part="row" wire:key="import-row-{{ $reportRow['line'] }}" data-import-error="{{ $reportRow['line'] }}">
                                                    <td data-scope="table" data-part="cell" data-numeric><bdi dir="ltr" class="ec-money">{{ $reportRow['line'] }}</bdi></td>
                                                    <td data-scope="table" data-part="cell" class="ec-cell-wrap">
                                                        {{ implode( ' ', $reportRow['errors'] ) }}
                                                        <span class="ec-muted ec-tax-import-row"><bdi dir="ltr">{{ $reportRow['summary'] }}</bdi></span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @if ( $importReport['invalid'] > count( $importReport['rows'] ) )
                                <p class="ec-muted">{{ trans_choice( 'And :count more row with errors.|And :count more rows with errors.', $importReport['invalid'] - count( $importReport['rows'] ), [ 'count' => $importReport['invalid'] - count( $importReport['rows'] ) ] ) }}</p>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="closeImport" data-focus-return="tax-import">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                <x-artisanpack-button variant="outline" color="neutral" wire:click="checkImport" wire:loading.attr="disabled" wire:target="checkImport, ratesCsv">{{ __( 'Check file' ) }}</x-artisanpack-button>
                @if ( $importCount > 0 )
                    <x-artisanpack-button
                        wire:click="applyImport( {{ Js::from( $importToken ) }} )"
                        wire:loading.attr="disabled"
                        data-autofocus
                        data-apply-import>{{ trans_choice( 'Import :count rate|Import :count rates', $importCount, [ 'count' => $importCount ] ) }}</x-artisanpack-button>
                @endif
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
