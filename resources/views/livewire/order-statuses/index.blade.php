{{--
    Order sub-statuses. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\OrderStatuses\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-6">
    <x-artisanpack-header :title="__( 'Order statuses' )" :subtitle="__( 'Sub-statuses refine each order status. Order screens and kanban columns use them.' )" :level="1" separator />

    @foreach ( $groups as $group )
        <section wire:key="system-status-{{ $group['status'] }}" class="flex flex-col gap-2" aria-labelledby="system-status-{{ $group['status'] }}-heading" data-system-status="{{ $group['status'] }}">
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="system-status-{{ $group['status'] }}-heading" class="grow text-lg font-semibold">
                    <x-artisanpack-ec-status-badge type="system" :value="$group['status']" />
                    <span class="sr-only">{{ $group['label'] }}</span>
                </h2>
                @if ( $canCreate )
                    <x-artisanpack-button
                        size="sm"
                        variant="outline"
                        icon="o-plus"
                        wire:click="create( {{ \Illuminate\Support\Js::from( $group['status'] ) }} )"
                        :label="__( 'Add sub-status' )"
                        :aria-label="__( 'Add sub-status to :status', [ 'status' => $group['label'] ] )"
                    />
                @endif
            </div>

            <ul class="flex list-none flex-col divide-y divide-base-300 rounded-box border border-base-300" aria-label="{{ __( ':status sub-statuses', [ 'status' => $group['label'] ] ) }}">
                @foreach ( $group['substatuses'] as $substatus )
                    @php $orders = (int) ( $orderCounts[ $substatus->id ] ?? 0 ); @endphp
                    <li wire:key="substatus-{{ $substatus->id }}" class="flex flex-wrap items-center gap-3 p-3" data-substatus="{{ $substatus->key }}">
                        <span
                            class="size-5 shrink-0 rounded border border-base-300"
                            style="background-color: {{ \ArtisanPackUI\EcommerceAdminLivewire\Support\ColorContrast::safeHex( $substatus->color ) }}"
                            aria-hidden="true"
                        ></span>

                        @if ( \ArtisanPackUI\EcommerceAdminLivewire\Support\IconChoices::exists( $substatus->icon ) )
                            <x-artisanpack-icon :name="$substatus->icon" class="size-4" aria-hidden="true" />
                        @endif

                        <div class="min-w-0 grow">
                            <x-artisanpack-ec-status-badge :substatus="$substatus" />
                            <code class="ms-2 text-sm opacity-75">{{ $substatus->key }}</code>
                            @if ( $substatus->is_terminal )
                                <span class="ms-2 text-xs opacity-75" data-terminal>{{ __( 'Terminal' ) }}</span>
                            @endif
                        </div>

                        <span class="text-sm opacity-75" data-order-count="{{ $orders }}">{{ trans_choice( ':count order|:count orders', $orders, [ 'count' => $orders ] ) }}</span>

                        <div class="flex items-center gap-1">
                            @if ( $canUpdate )
                                <x-artisanpack-button
                                    variant="ghost"
                                    size="sm"
                                    icon="o-arrow-up"
                                    wire:click="move( {{ $substatus->id }}, -1 )" data-reorder="up" data-reorder-list="substatuses-{{ $group['status'] }}" data-reorder-key="substatus-{{ $substatus->id }}" data-reorder-item="{{ $substatus->label }}"
                                    :disabled="$loop->first"
                                    :aria-label="__( 'Move :name up', [ 'name' => $substatus->label ] )"
                                />
                                <x-artisanpack-button
                                    variant="ghost"
                                    size="sm"
                                    icon="o-arrow-down"
                                    wire:click="move( {{ $substatus->id }}, 1 )" data-reorder="down" data-reorder-list="substatuses-{{ $group['status'] }}" data-reorder-key="substatus-{{ $substatus->id }}" data-reorder-item="{{ $substatus->label }}"
                                    :disabled="$loop->last"
                                    :aria-label="__( 'Move :name down', [ 'name' => $substatus->label ] )"
                                />
                                <x-artisanpack-button variant="ghost" size="sm" icon="o-pencil" wire:click="edit( {{ $substatus->id }} )" :aria-label="__( 'Edit :name', [ 'name' => $substatus->label ] )" />
                            @endif
                            @if ( $canDelete )
                                <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="confirmDelete( {{ $substatus->id }} )" :aria-label="__( 'Delete :name', [ 'name' => $substatus->label ] )" />
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    <x-artisanpack-drawer
        wire:model="editing"
        :title="null === $substatusId ? __( 'New sub-status' ) : __( 'Edit sub-status' )"
        right
        separator
        with-close-button
        close-on-escape
        class="w-full max-w-lg"
    >
        <form wire:submit="save" class="flex flex-col gap-4" data-substatus-form>
            @include( 'ecommerce-admin::partials.error-summary' )
            <p class="text-sm">{{ __( 'Order status: :status', [ 'status' => $formStatusLabel ] ) }}</p>

            <x-artisanpack-input id="substatus-label" :label="__( 'Label' )" wire:model="form.label" maxlength="120" required />

            @if ( null === $substatusId )
                <x-artisanpack-input id="substatus-key" :label="__( 'Key' )" :hint="__( 'Lowercase, like awaiting-label. Leave empty to make one from the label. It cannot be changed later.' )" wire:model="form.key" maxlength="80" />
            @else
                <x-artisanpack-input id="substatus-key" :label="__( 'Key' )" :hint="__( 'Automations and integrations refer to the key, so it cannot be changed.' )" :value="$form['key']" readonly />
            @endif

            <x-artisanpack-colorpicker id="substatus-color" :label="__( 'Colour' )" :hint="__( 'A hex code like #3B82F6. Leave empty for a neutral badge.' )" wire:model.live.debounce.300ms="form.color" />

            @if ( null !== $contrastWarning )
                <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" role="status" data-contrast-warning>
                    {{ $contrastWarning }}
                </x-artisanpack-alert>
            @endif

            @if ( $iconPicker )
                <div data-icon-picker>
                    <x-artisanpack-choices-offline
                        id="substatus-icon"
                        :label="__( 'Icon' )"
                        :options="$iconOptions"
                        single
                        searchable
                        clearable
                        :no-result-text="__( 'No results found.' )"
                        wire:model="form.icon"
                    />
                </div>
            @else
                <x-artisanpack-input id="substatus-icon" :label="__( 'Icon' )" :hint="__( 'An icon name, such as o-printer. Install artisanpack-ui/icons to pick from a list.' )" wire:model="form.icon" maxlength="80" data-icon-text />
            @endif

            <x-artisanpack-toggle id="substatus-terminal" :label="__( 'Terminal' )" :hint="__( 'Orders on a terminal sub-status are finished. Kanban boards treat them as done.' )" wire:model="form.is_terminal" />

            @if ( '' !== trim( (string) $form['label'] ) )
                <div class="flex items-center gap-2" data-substatus-preview>
                    <span class="text-sm">{{ __( 'Preview:' ) }}</span>
                    <x-artisanpack-ec-status-badge :substatus="new \ArtisanPackUI\Ecommerce\Models\OrderSubstatus( [ 'label' => $form['label'], 'color' => strtoupper( trim( (string) $form['color'] ) ) ] )" />
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <x-artisanpack-button variant="ghost" wire:click="$set( 'editing', false )" :label="__( 'Cancel' )" />
                <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" :label="__( 'Save sub-status' )" />
            </div>
        </form>
    </x-artisanpack-drawer>

    @if ( null !== $deleting )
        @php $deleteTitle = __( 'Delete ":name"?', [ 'name' => $deleting->label ] ); @endphp
        <x-artisanpack-modal wire:model="confirmingDelete" :title="$deleteTitle" separator>
            <div class="flex flex-col gap-3" data-delete-substatus="{{ $deleting->key }}">
                @if ( $deletingIsLast )
                    <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" role="status" data-delete-last>
                        {{ __( 'Every order status needs at least one sub-status, so the last one can\'t be deleted.' ) }}
                    </x-artisanpack-alert>
                @elseif ( array_sum( $deletingUsage ) > 0 )
                    <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" role="status" data-delete-in-use>
                        {{ __( 'It is still in use by :orders, :cards, and :columns. Move them to another sub-status first.', [
                            'orders'  => trans_choice( ':count order|:count orders', $deletingUsage['orders'], [ 'count' => $deletingUsage['orders'] ] ),
                            'cards'   => trans_choice( ':count board card|:count board cards', $deletingUsage['assignments'], [ 'count' => $deletingUsage['assignments'] ] ),
                            'columns' => trans_choice( ':count kanban column|:count kanban columns', $deletingUsage['columns'], [ 'count' => $deletingUsage['columns'] ] ),
                        ] ) }}
                    </x-artisanpack-alert>
                @else
                    <p>{{ __( 'Nothing uses this sub-status. Deleting it cannot be undone.' ) }}</p>
                @endif
            </div>

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" wire:click="cancelDelete" :label="__( 'Keep sub-status' )" />
                <x-artisanpack-button
                    color="error"
                    wire:click="delete( {{ \Illuminate\Support\Js::from( $deleteToken ) }} )"
                    wire:loading.attr="disabled"
                    :disabled="$deleteBlocked"
                    :label="__( 'Delete sub-status' )"
                    data-delete-button
                />
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif
</div>
