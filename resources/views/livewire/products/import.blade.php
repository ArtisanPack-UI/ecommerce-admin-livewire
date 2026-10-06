{{--
    Product CSV import. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Import.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;

    $indexRoute = AdminNav::ROUTE_PREFIX . 'products.index';
    $actionLabels = [
        'create'         => __( 'Create product' ),
        'update'         => __( 'Update product' ),
        'create-variant' => __( 'Create variant' ),
        'update-variant' => __( 'Update variant' ),
    ];
@endphp
<div @if ( in_array( $step, [ 'queued', 'running' ], true ) ) wire:poll.5s @endif>
    <x-artisanpack-header :title="__( 'Import products' )" :level="1" separator>
        <x-slot:actions>
            <x-artisanpack-button variant="ghost" icon="o-document-arrow-down" wire:click="downloadSample" :label="__( 'Download a sample file' )" />
            @if ( \Illuminate\Support\Facades\Route::has( $indexRoute ) )
                <x-artisanpack-button variant="outline" :link="route( $indexRoute )" :label="__( 'Back to products' )" />
            @endif
        </x-slot:actions>
    </x-artisanpack-header>

    <ol class="steps mb-6 w-full" aria-label="{{ __( 'Import steps' ) }}">
        @foreach ( [ 'upload' => __( 'Upload' ), 'mapping' => __( 'Match columns' ), 'checked' => __( 'Check' ), 'running' => __( 'Import' ) ] as $key => $label )
            @php
                $order   = [ 'upload' => 0, 'mapping' => 1, 'checked' => 2, 'queued' => 3, 'running' => 3, 'failed' => 3, 'completed' => 4 ];
                $current = $order[ $step ] ?? 0;
                $index   = $order[ $key ];
            @endphp
            <li @class( [ 'step', 'step-primary' => $index <= $current ] ) @if ( $index === $current ) aria-current="step" @endif>{{ $label }}</li>
        @endforeach
    </ol>

    @if ( 'upload' === $step )
        <form wire:submit="upload" class="flex max-w-xl flex-col gap-4" data-import-upload>
            <p>{{ __( 'Upload a CSV of products. Rows match existing products on SKU, then slug; anything else is created. At most :count rows and :size MB.', [ 'count' => $maxRows, 'size' => $maxUploadMb ] ) }}</p>
            <x-artisanpack-file id="import-file" :label="__( 'CSV file' )" accept=".csv,text/csv,text/plain" wire:model="csv" />
            <div>
                <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" :label="__( 'Upload' )" />
            </div>
        </form>
    @elseif ( 'mapping' === $step )
        <form wire:submit="check" class="flex flex-col gap-4" data-import-mapping>
            <p>{{ __( 'Choose the column each header in ":file" fills. Ignored headers are skipped.', [ 'file' => $state['file_name'] ] ) }}</p>
            @error( 'mapping' )
                <x-artisanpack-alert color="error" icon="o-exclamation-circle" :title="$message" role="alert" />
            @enderror
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                @foreach ( $state['headers'] as $index => $header )
                    <x-artisanpack-select
                        wire:key="import-map-{{ $index }}"
                        id="import-map-{{ $index }}"
                        :label="$header"
                        :options="$columnOptions"
                        :placeholder="__( 'Ignore this column' )"
                        placeholder-value=""
                        wire:model="mapping.{{ $index }}"
                    />
                @endforeach
            </div>
            <div class="flex gap-2">
                <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" :label="__( 'Check the file' )" />
                <x-artisanpack-button variant="ghost" wire:click="discard" :label="__( 'Start over' )" />
            </div>
        </form>
    @elseif ( 'checked' === $step )
        <div class="flex flex-col gap-4" data-import-report>
            <ul class="flex flex-wrap gap-x-6 gap-y-1 font-semibold" role="status" data-import-counts>
                <li>{{ trans_choice( ':count new product or variant|:count new products or variants', (int) ( $reportCounts['create'] ?? 0 ), [ 'count' => (int) ( $reportCounts['create'] ?? 0 ) ] ) }}</li>
                <li>{{ trans_choice( ':count update|:count updates', (int) ( $reportCounts['update'] ?? 0 ), [ 'count' => (int) ( $reportCounts['update'] ?? 0 ) ] ) }}</li>
                <li @class( [ 'text-error' => (int) ( $reportCounts['error'] ?? 0 ) > 0 ] )>{{ trans_choice( ':count row with errors|:count rows with errors', (int) ( $reportCounts['error'] ?? 0 ), [ 'count' => (int) ( $reportCounts['error'] ?? 0 ) ] ) }}</li>
            </ul>

            @if ( [] !== $reportErrors )
                <div class="overflow-x-auto">
                    <table class="table table-sm" data-import-errors>
                        <caption class="text-start font-semibold">{{ __( 'Rows with errors (skipped on import)' ) }}</caption>
                        <thead><tr><th scope="col">{{ __( 'Line' ) }}</th><th scope="col">{{ __( 'Row' ) }}</th><th scope="col">{{ __( 'Problem' ) }}</th></tr></thead>
                        <tbody>
                            @foreach ( $reportErrors as $row )
                                <tr wire:key="import-error-{{ $row['line'] }}" class="text-error"><td class="tabular-nums">{{ $row['line'] }}</td><td>{{ $row['label'] }}</td><td>{{ $row['error'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ( [] !== $reportPreview )
                <div class="overflow-x-auto">
                    <table class="table table-sm" data-import-preview>
                        <caption class="text-start font-semibold">{{ __( 'Rows to import' ) }}</caption>
                        <thead><tr><th scope="col">{{ __( 'Line' ) }}</th><th scope="col">{{ __( 'Row' ) }}</th><th scope="col">{{ __( 'Action' ) }}</th></tr></thead>
                        <tbody>
                            @foreach ( $reportPreview as $row )
                                <tr wire:key="import-row-{{ $row['line'] }}"><td class="tabular-nums">{{ $row['line'] }}</td><td>{{ $row['label'] }}</td><td>{{ $actionLabels[ $row['action'] ] ?? $row['action'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex flex-wrap gap-2">
                @if ( (int) ( $reportCounts['create'] ?? 0 ) + (int) ( $reportCounts['update'] ?? 0 ) > 0 )
                    <x-artisanpack-button color="primary" wire:click="apply( {{ \Illuminate\Support\Js::from( $applyToken ) }} )" wire:loading.attr="disabled" :label="__( 'Import' )" />
                @endif
                <x-artisanpack-button variant="outline" wire:click="editMapping" :label="__( 'Change the column matching' )" />
                <x-artisanpack-button variant="ghost" wire:click="discard" :label="__( 'Start over' )" />
            </div>
        </div>
    @else
        <div class="flex max-w-2xl flex-col gap-4" data-import-progress="{{ $step }}">
            <x-artisanpack-progress :value="$progress" max="100" class="progress-primary w-full" aria-label="{{ __( 'Import progress' ) }}" />
            <p role="status">
                {{ __( ':processed of :total rows done: :created created, :updated updated, :failed failed.', [
                    'processed' => $state['processed'],
                    'total'     => $state['total'],
                    'created'   => $state['counts']['created'],
                    'updated'   => $state['counts']['updated'],
                    'failed'    => $state['counts']['failed'],
                ] ) }}
            </p>

            @if ( 'queued' === $step )
                <p class="text-sm opacity-75">{{ __( 'Waiting for the queue to start the import.' ) }}</p>
            @endif

            @if ( filled( $state['message'] ) )
                <x-artisanpack-alert :color="'failed' === $step ? 'error' : 'info'" icon="o-information-circle" :title="$state['message']" role="status" />
            @endif

            <div class="flex flex-wrap gap-2">
                @if ( 'failed' === $step && $state['processed'] < $state['total'] )
                    <x-artisanpack-button color="primary" icon="o-play" wire:click="resume" wire:loading.attr="disabled" :label="__( 'Resume import' )" />
                @endif
                @if ( (int) $state['counts']['failed'] > 0 )
                    <x-artisanpack-button variant="outline" icon="o-arrow-down-tray" wire:click="downloadErrors" :label="__( 'Download the error report' )" />
                @endif
                @if ( in_array( $step, [ 'completed', 'failed' ], true ) )
                    <x-artisanpack-button variant="ghost" wire:click="discard" :label="'completed' === $step ? __( 'Import another file' ) : __( 'Discard this import' )" />
                @endif
            </div>
        </div>
    @endif
</div>
