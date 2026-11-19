{{--
    Products list (#119): the category and tag the bulk bar adds or removes,
    each select followed by its own Add and Remove buttons. The buttons run
    the add/remove-category and add/remove-tag bulk actions, which the bar
    leaves out of its own row ($bulkInlineActions); one a satellite removed,
    or the user may not run, is not shown.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    $bulkGroups = [
        [
            'id'          => 'products-bulk-category',
            'model'       => 'bulkCategoryId',
            'label'       => __( 'Category to add or remove' ),
            'options'     => $bulkCategoryOptions,
            'placeholder' => [] === $bulkCategoryOptions ? __( 'No categories yet' ) : __( 'Choose a category' ),
            'actions'     => [ 'add-category' => __( 'Add' ), 'remove-category' => __( 'Remove' ) ],
        ],
        [
            'id'          => 'products-bulk-tag',
            'model'       => 'bulkTagId',
            'label'       => __( 'Tag to add or remove' ),
            'options'     => $bulkTagOptions,
            'placeholder' => [] === $bulkTagOptions ? __( 'No tags yet' ) : __( 'Choose a tag' ),
            'actions'     => [ 'add-tag' => __( 'Add' ), 'remove-tag' => __( 'Remove' ) ],
        ],
    ];
@endphp
@foreach ( $bulkGroups as $group )
    @php( $groupActions = array_intersect_key( $tableBulkActions, $group['actions'] ) )
    @if ( [] !== $groupActions )
        <div class="ec-products-bulk-group" role="group" aria-label="{{ $group['label'] }}" wire:key="{{ $group['id'] }}-group">
            <div class="ec-products-bulk-select">
                <x-artisanpack-select
                    :id="$group['id']"
                    size="sm"
                    :aria-label="$group['label']"
                    :options="\ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions::from( $group['options'] )"
                    :placeholder="$group['placeholder']"
                    :disabled="[] === $group['options']"
                    wire:model="{{ $group['model'] }}"
                />
            </div>
            @foreach ( $groupActions as $key => $action )
                {{-- Visible "Add"/"Remove", named in full ("Add category") so the name starts with the visible text. --}}
                <x-artisanpack-button
                    variant="outline"
                    color="neutral"
                    size="sm"
                    :aria-label="$action['label']"
                    :disabled="[] === $group['options']"
                    wire:click="runBulkAction( {{ \Illuminate\Support\Js::from( $key ) }} )"
                    wire:loading.attr="disabled"
                    data-focus-key="bulk-{{ $key }}"
                    wire:key="bulk-action-{{ $key }}"
                >{{ $group['actions'][ $key ] }}</x-artisanpack-button>
            @endforeach
        </div>
    @endif
@endforeach
