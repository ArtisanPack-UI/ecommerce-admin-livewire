{{--
    Tax classes and rates. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Tax\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-6">
    <x-artisanpack-header :title="__( 'Tax' )" :level="1" separator />

    <div data-tax-provider="{{ $provider['key'] }}">
        @if ( $provider['manual'] )
            <x-artisanpack-alert
                color="info"
                icon="o-information-circle"
                :title="__( 'Taxes are calculated by :provider.', [ 'provider' => $provider['label'] ] )"
                :description="__( 'Checkout uses the active rates below.' )"
                role="status"
            />
        @else
            <x-artisanpack-alert
                color="warning"
                icon="o-exclamation-triangle"
                :title="__( 'These rates are not used.' )"
                :description="__( 'Taxes are calculated by :provider, so checkout ignores the rates table. Change the tax provider in the store settings to use them.', [ 'provider' => $provider['label'] ] )"
                role="status"
                data-rates-unused
            />
        @endif
    </div>

    <section class="flex flex-col gap-3" aria-labelledby="tax-classes-heading" data-tax-classes>
        <h2 id="tax-classes-heading" class="text-lg font-semibold">{{ __( 'Tax classes' ) }}</h2>
        <p class="text-sm opacity-75">{{ __( 'Products and shipping methods choose a class; each rate applies to one class.' ) }}</p>

        <ul class="flex list-none flex-col divide-y divide-base-300 rounded-box border border-base-300">
            @foreach ( $classes as $class )
                @php $uses = $classUsage[ $class->key ]; @endphp
                <li wire:key="tax-class-{{ $class->id }}" class="flex flex-wrap items-center gap-3 p-3" data-tax-class="{{ $class->key }}">
                    @if ( $editingClassId === (int) $class->id )
                        <form wire:submit="saveClass" class="flex grow flex-wrap items-end gap-2" data-edit-class="{{ $class->key }}">
                            <x-artisanpack-input id="tax-class-{{ $class->id }}-label" class="input-sm" :label="__( 'Label' )" wire:model="editingClassLabel" />
                            <x-artisanpack-button type="submit" size="sm" color="primary" :label="__( 'Save' )" />
                            <x-artisanpack-button size="sm" variant="ghost" wire:click="cancelClass" :label="__( 'Cancel' )" />
                        </form>
                    @else
                        <div class="min-w-0 grow">
                            <span class="font-semibold">{{ $class->label }}</span>
                            <code class="ms-2 text-sm opacity-75">{{ $class->key }}</code>
                        </div>
                        <span class="text-sm opacity-75">
                            {{ trans_choice( ':count rate|:count rates', $uses['rates'], [ 'count' => $uses['rates'] ] ) }} ·
                            {{ trans_choice( ':count product|:count products', $uses['products'], [ 'count' => $uses['products'] ] ) }}
                            @if ( $uses['methods'] > 0 )
                                · {{ trans_choice( ':count shipping method|:count shipping methods', $uses['methods'], [ 'count' => $uses['methods'] ] ) }}
                            @endif
                        </span>
                        <div class="flex gap-1">
                            @if ( $canUpdate )
                                <x-artisanpack-button variant="ghost" size="sm" icon="o-pencil" wire:click="editClass( {{ $class->id }} )" :aria-label="__( 'Edit :name', [ 'name' => $class->label ] )" />
                            @endif
                            @if ( $canDelete )
                                <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="confirmDeleteClass( {{ $class->id }} )" :aria-label="__( 'Delete :name', [ 'name' => $class->label ] )" />
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ( $canCreate )
            <form wire:submit="createClass" class="flex flex-wrap items-end gap-3" data-new-class>
                <x-artisanpack-input id="new-tax-class-key" :label="__( 'Key' )" :hint="__( 'Lowercase, like reduced-rate. It cannot be changed later.' )" wire:model="newClassKey" maxlength="60" />
                <x-artisanpack-input id="new-tax-class-label" :label="__( 'Label' )" wire:model="newClassLabel" maxlength="120" />
                <x-artisanpack-button type="submit" icon="o-plus" wire:loading.attr="disabled" :label="__( 'Add class' )" />
            </form>
        @endif
    </section>

    <section class="flex flex-col gap-3" aria-labelledby="tax-rates-heading" data-tax-rates>
        <div class="flex flex-wrap items-center gap-2">
            <h2 id="tax-rates-heading" class="grow text-lg font-semibold">{{ __( 'Tax rates' ) }}</h2>
            <x-artisanpack-button variant="outline" size="sm" icon="o-arrow-down-tray" wire:click="exportRates" :label="__( 'Download rates CSV' )" />
            @if ( $canCreate )
                <x-artisanpack-button variant="outline" size="sm" icon="o-arrow-up-tray" wire:click="openImport" :label="__( 'Import CSV' )" />
                <x-artisanpack-button color="primary" size="sm" icon="o-plus" wire:click="createRate" :label="__( 'New rate' )" />
            @endif
        </div>

        @if ( $creatingRate )
            <form wire:submit="saveRate" class="grid gap-3 rounded-box border border-base-300 p-4 sm:grid-cols-2 lg:grid-cols-5" data-new-rate>
                <x-artisanpack-select id="new-rate-class" :label="__( 'Tax class' )" :options="$classOptions" wire:model="rateForm.tax_class_key" />
                <x-artisanpack-select id="new-rate-country" :label="__( 'Country' )" :options="$countryOptions" :placeholder="__( 'Choose a country' )" placeholder-value="" wire:model="rateForm.country_code" />
                <x-artisanpack-input id="new-rate-region" :label="__( 'Region' )" :hint="__( 'A state or province code, like NY. Leave empty for the whole country.' )" maxlength="10" wire:model="rateForm.region_code" />
                <x-artisanpack-input id="new-rate-postal" :label="__( 'Postal codes' )" :hint="__( 'Comma-separated; * matches anything and 10001...10099 is a range. Leave empty for all.' )" maxlength="60" wire:model="rateForm.postal_pattern" />
                <x-artisanpack-ec-percent-input id="new-rate-rate" :label="__( 'Rate' )" wire:model="rateForm.rate_ubps" />
                <x-artisanpack-input id="new-rate-label" :label="__( 'Label' )" :hint="__( 'Shown to shoppers, like Sales tax or VAT.' )" maxlength="120" wire:model="rateForm.label" />
                <x-artisanpack-input id="new-rate-priority" type="number" step="1" :label="__( 'Priority' )" :hint="__( 'Rates with different priorities for the same place stack.' )" wire:model="rateForm.priority" />
                <div class="self-center">
                    <x-artisanpack-toggle id="new-rate-compound" :label="__( 'Compound' )" :hint="__( 'Charged on top of the other taxes.' )" wire:model="rateForm.is_compound" />
                </div>
                <div class="self-center">
                    <x-artisanpack-toggle id="new-rate-shipping" :label="__( 'Taxes shipping' )" wire:model="rateForm.is_shipping_taxable" />
                </div>
                <div class="self-center">
                    <x-artisanpack-toggle id="new-rate-active" :label="__( 'Active' )" wire:model="rateForm.is_active" />
                </div>
                <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
                    <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" :label="__( 'Save rate' )" />
                    <x-artisanpack-button variant="ghost" wire:click="cancelRate" :label="__( 'Cancel' )" />
                </div>
            </form>
        @endif

        @include( 'ecommerce-admin::partials.resource-table', [
            'emptyIcon'        => 'o-receipt-percent',
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
    </section>

    @if ( null !== $deletingClass )
        @php $deleteClassTitle = __( 'Delete ":name"?', [ 'name' => $deletingClass->label ] ); @endphp
        <x-artisanpack-modal wire:model="confirmingClassDelete" :title="$deleteClassTitle" separator>
            <div data-delete-class="{{ $deletingClass->key }}">
                @if ( null !== $deletingBlocker )
                    <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" :title="__( 'This class cannot be deleted yet.' )" :description="$deletingBlocker" role="status" data-class-blocked />
                @else
                    <p>{{ __( 'Nothing uses this class. Deleting it cannot be undone.' ) }}</p>
                @endif
            </div>

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" wire:click="cancelDeleteClass" :label="__( 'Keep class' )" />
                @if ( null === $deletingBlocker )
                    <x-artisanpack-button
                        color="error"
                        wire:click="deleteClass( {{ \Illuminate\Support\Js::from( $deleteClassToken ) }} )"
                        wire:loading.attr="disabled"
                        :label="__( 'Delete class' )"
                    />
                @endif
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif

    @if ( $importing )
        <x-artisanpack-modal wire:model="importing" :title="__( 'Import tax rates' )" box-class="max-w-4xl" separator>
            <div class="flex flex-col gap-4" data-rates-import>
                <p class="text-sm">
                    {{ __( 'Upload a CSV with these columns: :columns. Only tax_class_key, country_code, rate_percent, and label are required. A row with the same class, country, region, postal codes, and priority as an existing rate updates it; any other row adds a rate. At most :count rows.', [ 'columns' => implode( ', ', $csvColumns ), 'count' => $maxRows ] ) }}
                </p>

                <div class="flex flex-wrap items-end gap-3">
                    <x-artisanpack-file id="tax-rates-file" :label="__( 'CSV file' )" accept=".csv,text/csv,text/plain" wire:model="ratesCsv" />
                    <x-artisanpack-button variant="ghost" size="sm" icon="o-document-arrow-down" wire:click="downloadSample" :label="__( 'Download a sample' )" />
                </div>

                @if ( null !== $importReport )
                    <div class="flex flex-col gap-2" data-import-report>
                        <p class="font-semibold" role="status">
                            {{ __( 'Dry run: :create to add, :update to update.', [ 'create' => $importReport['create'], 'update' => $importReport['update'] ] ) }}
                            @if ( $importReport['invalid'] > 0 )
                                {{ trans_choice( ':count row has errors and will be skipped.|:count rows have errors and will be skipped.', $importReport['invalid'], [ 'count' => $importReport['invalid'] ] ) }}
                            @endif
                        </p>

                        @if ( [] !== $importReport['rows'] )
                            <div class="overflow-x-auto">
                                <table class="table table-sm">
                                    <caption class="sr-only">{{ __( 'Rows with errors' ) }}</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ __( 'Line' ) }}</th>
                                            <th scope="col">{{ __( 'Row' ) }}</th>
                                            <th scope="col">{{ __( 'Problem' ) }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ( $importReport['rows'] as $reportRow )
                                            <tr wire:key="import-row-{{ $reportRow['line'] }}" class="text-error" data-import-error="{{ $reportRow['line'] }}">
                                                <td class="tabular-nums">{{ $reportRow['line'] }}</td>
                                                <td>{{ $reportRow['summary'] }}</td>
                                                <td>{{ implode( ' ', $reportRow['errors'] ) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if ( $importReport['invalid'] > count( $importReport['rows'] ) )
                                <p class="text-sm">{{ trans_choice( 'And :count more row with errors.|And :count more rows with errors.', $importReport['invalid'] - count( $importReport['rows'] ), [ 'count' => $importReport['invalid'] - count( $importReport['rows'] ) ] ) }}</p>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" wire:click="closeImport" :label="__( 'Cancel' )" />
                <x-artisanpack-button variant="outline" wire:click="checkImport" wire:loading.attr="disabled" :label="__( 'Check file' )" />
                @if ( null !== $importReport && $importReport['create'] + $importReport['update'] > 0 )
                    <x-artisanpack-button
                        color="primary"
                        wire:click="applyImport( {{ \Illuminate\Support\Js::from( $importToken ) }} )"
                        wire:loading.attr="disabled"
                        :label="trans_choice( 'Import :count rate|Import :count rates', $importReport['create'] + $importReport['update'], [ 'count' => $importReport['create'] + $importReport['update'] ] )"
                    />
                @endif
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif
</div>
