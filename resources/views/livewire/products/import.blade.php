{{--
    Product import (#121). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Import.

    A four-step CSV import in one card: upload, match each header to a
    product field, check what will be created, updated or skipped, then
    import on the queue with live progress. Each step has one solid action
    and "Start over" is always a ghost button.

    - Upload: a dropzone and the chosen file in a file list.
    - Match columns: a table of each header, its first value and the field
      it fills. "Check the file" stays unavailable until a column fills a
      key the rows can be matched on.
    - Check: Stat counts of creates, updates and errors, then the error rows
      (skipped, not fatal) and the rows to import, before anything is
      written.
    - Import: a labelled progressbar with a live sentence below it, polled
      every five seconds. A finished import with failures offers the error
      report as its primary action.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Js;

    $indexRoute = AdminNav::ROUTE_PREFIX . 'products.index';
    $actions    = [
        'create'         => [ 'label' => __( 'Create product' ), 'color' => 'success' ],
        'update'         => [ 'label' => __( 'Update product' ), 'color' => 'info' ],
        'create-variant' => [ 'label' => __( 'Create variant' ), 'color' => 'success' ],
        'update-variant' => [ 'label' => __( 'Update variant' ), 'color' => 'info' ],
    ];

    $position = [ 'upload' => 1, 'mapping' => 2, 'checked' => 3, 'queued' => 4, 'running' => 4, 'failed' => 4, 'completed' => 4 ][ $step ] ?? 1;
    $steps    = [];

    foreach ( [ __( 'Upload' ), __( 'Match columns' ), __( 'Check' ), __( 'Import' ) ] as $index => $title ) {
        $number  = $index + 1;
        $steps[] = [
            'title' => $title,
            'state' => $number < $position ? 'complete' : ( $number === $position ? 'current' : 'upcoming' ),
        ];
    }
@endphp
<div class="ec-stack" data-product-import @if ( in_array( $step, [ 'queued', 'running' ], true ) ) wire:poll.5s @endif>
    <x-artisanpack-page-header :title="__( 'Import products' )" :description="__( 'Add or update products in bulk from a CSV file.' )" :level="1">
        <x-slot:actions>
            <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="download" wire:click="downloadSample">{{ __( 'Download a sample file' ) }}</x-artisanpack-button>
            @if ( Route::has( $indexRoute ) )
                <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="chevron-left" class="ec-icon-mirror" :href="route( $indexRoute )">{{ __( 'Back to products' ) }}</x-artisanpack-button>
            @endif
        </x-slot:actions>
    </x-artisanpack-page-header>

    <x-artisanpack-card :classes="[ 'body' => 'ec-stack-lg' ]">
        <x-artisanpack-steps :steps="$steps" :label="__( 'Import steps' )" class="ec-import-steps" data-import-steps />

        <x-artisanpack-divider decorative />

        @if ( 'upload' === $step )
            <form wire:submit="uploadCsv" class="ec-stack" data-import-upload>
                <p class="ec-muted ec-measure">{{ __( 'Upload a CSV of products. Rows match existing products on SKU, then slug; anything else is created. At most :count rows and :size MB.', [ 'count' => $maxRows, 'size' => $maxUploadMb ] ) }}</p>

                <x-artisanpack-file
                    id="import-file"
                    :title="__( 'Drop a CSV here or choose a file' )"
                    :hint="__( '.csv up to :size MB', [ 'size' => $maxUploadMb ] )"
                    accept=".csv,text/csv,text/plain"
                    wire:model="csv"
                />

                <div>
                    <x-artisanpack-button type="submit" leading-icon="download" wire:loading.attr="disabled" wire:target="uploadCsv, csv" data-import-upload-submit>{{ __( 'Upload' ) }}</x-artisanpack-button>
                </div>
            </form>
        @elseif ( 'mapping' === $step )
            <form wire:submit="check" class="ec-stack" data-import-mapping>
                <p class="ec-muted ec-measure">{{ __( 'Choose the column each header in ":file" fills. Ignored headers are skipped.', [ 'file' => $state['file_name'] ] ) }}</p>

                @error( 'mapping' )
                    <x-artisanpack-alert color="error" icon="circle-alert">{{ $message }}</x-artisanpack-alert>
                @else
                    @unless ( $hasKeyColumn )
                        <x-artisanpack-alert color="warning" icon="triangle-alert" id="import-needs-key" data-import-needs-key>{{ __( 'Map at least one of SKU, slug, name, or variant SKU so rows can be matched.' ) }}</x-artisanpack-alert>
                    @endunless
                @enderror

                <div class="apui-table ec-table">
                    <div data-scope="table" data-part="scroll">
                        <table data-scope="table" data-part="table" data-import-columns>
                            <caption class="apui-sr-only">{{ __( 'Column matching' ) }}</caption>
                            <thead>
                                <tr>
                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Header in the file' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'First row' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Fills' ) }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ( $state['headers'] as $index => $header )
                                    @php
                                        $firstValue = (string) ( $sample[ $index ] ?? '' );
                                    @endphp
                                    <tr data-scope="table" data-part="row" wire:key="import-map-row-{{ $index }}">
                                        <th data-scope="table" data-part="cell" scope="row"><bdi dir="ltr" class="ec-mono">{{ $header }}</bdi></th>
                                        <td data-scope="table" data-part="cell" class="ec-cell-muted ec-import-sample">{{ '' === $firstValue ? '—' : $firstValue }}</td>
                                        <td data-scope="table" data-part="cell" class="ec-import-fills">
                                            <x-artisanpack-select
                                                id="import-map-{{ $index }}"
                                                size="sm"
                                                :options="ControlOptions::from( $columnOptions )"
                                                :placeholder="__( 'Ignore this column' )"
                                                :aria-label="__( 'Column for :header', [ 'header' => $header ] )"
                                                :error-field="'mapping.' . $index"
                                                wire:model.live="mapping.{{ $index }}"
                                            />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="ec-actions">
                    @if ( $hasKeyColumn )
                        <x-artisanpack-button type="submit" wire:loading.attr="disabled" wire:target="check" data-import-check>{{ __( 'Check the file' ) }}</x-artisanpack-button>
                    @else
                        <x-artisanpack-button aria-disabled="true" :aria-describedby="$errors->has( 'mapping' ) ? null : 'import-needs-key'" data-import-check>{{ __( 'Check the file' ) }}</x-artisanpack-button>
                    @endif
                    <x-artisanpack-button variant="ghost" color="neutral" wire:click="discard" data-import-start-over>{{ __( 'Start over' ) }}</x-artisanpack-button>
                </div>
            </form>
        @elseif ( 'checked' === $step )
            @php
                $creates   = (int) ( $reportCounts['create'] ?? 0 );
                $updates   = (int) ( $reportCounts['update'] ?? 0 );
                $failures  = (int) ( $reportCounts['error'] ?? 0 );
                $importing = $creates + $updates;
            @endphp
            <div class="ec-stack-lg" data-import-report>
                @if ( filled( $state['message'] ?? null ) )
                    <x-artisanpack-alert color="error" icon="circle-alert" data-import-message>{{ $state['message'] }}</x-artisanpack-alert>
                @endif

                @if ( $stockWarning )
                    <x-artisanpack-alert color="warning" icon="triangle-alert" data-import-stock-warning>{{ __( 'Stock columns will be skipped: you can\'t adjust stock.' ) }}</x-artisanpack-alert>
                @endif

                <div class="ec-import-counts" role="status" data-import-counts>
                    <x-artisanpack-stat size="sm" color="success" icon="plus" :value="(string) $creates" :label="trans_choice( 'new product or variant|new products or variants', $creates )" data-import-count="create" />
                    <x-artisanpack-stat size="sm" color="info" icon="refresh-cw" :value="(string) $updates" :label="trans_choice( 'update|updates', $updates )" data-import-count="update" />
                    <x-artisanpack-stat size="sm" :color="$failures > 0 ? 'error' : 'neutral'" :emphasis="$failures > 0" icon="triangle-alert" :value="(string) $failures" :label="trans_choice( 'row with errors|rows with errors', $failures )" data-import-count="error" />
                </div>

                @if ( [] !== $reportErrors )
                    <section class="ec-table-section" aria-labelledby="import-errors-heading">
                        <x-artisanpack-heading :level="2" :size="6" id="import-errors-heading">{{ __( 'Rows with errors (skipped on import)' ) }}</x-artisanpack-heading>
                        <div class="apui-table ec-table">
                            <div data-scope="table" data-part="scroll" data-density="compact">
                                <table data-scope="table" data-part="table" data-import-errors>
                                    <caption class="apui-sr-only">{{ __( 'Rows with errors (skipped on import)' ) }}</caption>
                                    <thead>
                                        <tr>
                                            <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Line' ) }}</th>
                                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Row' ) }}</th>
                                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Problem' ) }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ( $reportErrors as $row )
                                            <tr data-scope="table" data-part="row" wire:key="import-error-{{ $row['line'] }}">
                                                <td data-scope="table" data-part="cell" data-numeric>{{ $row['line'] }}</td>
                                                <td data-scope="table" data-part="cell" class="ec-cell-wrap">{{ $row['label'] }}</td>
                                                <td data-scope="table" data-part="cell" class="ec-cell-wrap ec-text-error">{{ $row['error'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                @endif

                @if ( [] !== $reportPreview )
                    <section class="ec-table-section" aria-labelledby="import-preview-heading">
                        <x-artisanpack-heading :level="2" :size="6" id="import-preview-heading">{{ __( 'Rows to import' ) }}</x-artisanpack-heading>
                        <div class="apui-table ec-table">
                            <div data-scope="table" data-part="scroll" data-density="compact">
                                <table data-scope="table" data-part="table" data-import-preview>
                                    <caption class="apui-sr-only">{{ __( 'Rows to import' ) }}</caption>
                                    <thead>
                                        <tr>
                                            <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Line' ) }}</th>
                                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Row' ) }}</th>
                                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Action' ) }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ( $reportPreview as $row )
                                            @php
                                                $action = $actions[ $row['action'] ] ?? [ 'label' => $row['action'], 'color' => 'neutral' ];
                                            @endphp
                                            <tr data-scope="table" data-part="row" wire:key="import-row-{{ $row['line'] }}">
                                                <td data-scope="table" data-part="cell" data-numeric>{{ $row['line'] }}</td>
                                                <td data-scope="table" data-part="cell" class="ec-cell-wrap">{{ $row['label'] }}</td>
                                                <td data-scope="table" data-part="cell"><x-artisanpack-badge :color="$action['color']" dot data-import-action="{{ $row['action'] }}">{{ $action['label'] }}</x-artisanpack-badge></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                @endif

                <div class="ec-actions">
                    @if ( $importing > 0 )
                        <x-artisanpack-button leading-icon="download" wire:click="apply( {{ Js::from( $applyToken ) }} )" wire:loading.attr="disabled" data-import-apply>{{ trans_choice( 'Import :count row|Import :count rows', $importing, [ 'count' => $importing ] ) }}</x-artisanpack-button>
                        <x-artisanpack-button variant="outline" color="neutral" wire:click="editMapping" data-import-edit-mapping>{{ __( 'Change the column matching' ) }}</x-artisanpack-button>
                    @else
                        <x-artisanpack-button wire:click="editMapping" data-import-edit-mapping>{{ __( 'Change the column matching' ) }}</x-artisanpack-button>
                    @endif
                    <x-artisanpack-button variant="ghost" color="neutral" wire:click="discard" data-import-start-over>{{ __( 'Start over' ) }}</x-artisanpack-button>
                </div>
            </div>
        @else
            @php
                $failed    = (int) $state['counts']['failed'];
                $resumable = 'failed' === $step && $state['processed'] < $state['total'];
                $barColor  = match ( $step ) {
                    'completed' => 'success',
                    'failed'    => 'error',
                    default     => 'primary',
                };
            @endphp
            <div class="ec-stack-lg" data-import-progress="{{ $step }}">
                <div class="ec-stack-sm">
                    <x-artisanpack-progress
                        :value="$progress"
                        :label="__( 'Import progress' )"
                        :color="$barColor"
                        data-import-progressbar
                    />
                    {{-- Not a live region: it changes on every poll. The sr-only status below speaks only on a status change or a quarter mark. --}}
                    <p class="ec-muted" data-import-sentence>
                        {{ __( ':processed of :total rows done: :created created, :updated updated, :failed failed.', [
                            'processed' => $state['processed'],
                            'total'     => $state['total'],
                            'created'   => $state['counts']['created'],
                            'updated'   => $state['counts']['updated'],
                            'failed'    => $failed,
                        ] ) }}
                    </p>
                    <p class="apui-sr-only" role="status" data-import-announcement>{{ $announcement }}</p>
                </div>

                @if ( 'queued' === $step )
                    <p class="ec-muted">{{ __( 'Waiting for the queue to start the import.' ) }}</p>
                @endif

                @if ( 'completed' === $step && $failed > 0 )
                    <x-artisanpack-alert color="warning" icon="triangle-alert" :title="__( 'Import finished' )" data-import-finished-with-failures>
                        {{ trans_choice( ':count row failed. Download the report to fix and re-upload it.|:count rows failed. Download the report to fix and re-upload them.', $failed, [ 'count' => $failed ] ) }}
                    </x-artisanpack-alert>
                @endif

                @if ( $stockSkipped > 0 )
                    <x-artisanpack-alert color="warning" icon="triangle-alert" data-import-stock-skipped>
                        {{ trans_choice( 'Stock columns were skipped on :count row: you can\'t adjust stock.|Stock columns were skipped on :count rows: you can\'t adjust stock.', $stockSkipped, [ 'count' => $stockSkipped ] ) }}
                    </x-artisanpack-alert>
                @endif

                @if ( filled( $state['message'] ) )
                    <x-artisanpack-alert :color="'failed' === $step ? 'error' : 'info'" :icon="'failed' === $step ? 'circle-alert' : 'info'" data-import-message>{{ $state['message'] }}</x-artisanpack-alert>
                @endif

                @if ( in_array( $step, [ 'completed', 'failed' ], true ) )
                    {{-- One solid action: resume a stopped import, else the error report, else another file. --}}
                    <div class="ec-actions">
                        @if ( $resumable )
                            <x-artisanpack-button leading-icon="play" wire:click="resume" wire:loading.attr="disabled" data-import-resume>{{ __( 'Resume import' ) }}</x-artisanpack-button>
                        @endif
                        @if ( $failed > 0 )
                            <x-artisanpack-button
                                :variant="$resumable ? 'outline' : 'solid'"
                                :color="$resumable ? 'neutral' : 'primary'"
                                leading-icon="download"
                                wire:click="downloadErrors"
                                data-import-download-errors>{{ __( 'Download the error report' ) }}</x-artisanpack-button>
                        @endif
                        @if ( 'completed' === $step )
                            <x-artisanpack-button
                                :variant="$failed > 0 ? 'outline' : 'solid'"
                                :color="$failed > 0 ? 'neutral' : 'primary'"
                                wire:click="discard"
                                data-import-another>{{ __( 'Import another file' ) }}</x-artisanpack-button>
                        @else
                            <x-artisanpack-button variant="ghost" color="error" wire:click="discard" data-import-discard>{{ __( 'Discard this import' ) }}</x-artisanpack-button>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </x-artisanpack-card>
</div>
