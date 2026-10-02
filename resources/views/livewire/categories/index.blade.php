{{--
    Category tree. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Categories\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Categories\Index;
@endphp
<div>
    <x-artisanpack-header :title="__( 'Categories' )" :level="1" separator>
        @if ( $canCreate )
            <x-slot:actions>
                <x-artisanpack-button color="primary" icon="o-plus" wire:click="create" :label="__( 'New category' )" />
            </x-slot:actions>
        @endif
    </x-artisanpack-header>

    @if ( 0 === $total )
        <x-artisanpack-ec-empty-state
            icon="o-folder"
            :title="__( 'No categories yet' )"
            :description="__( 'Group products into categories so shoppers can browse them.' )"
        />
    @else
        <ul class="flex list-none flex-col divide-y divide-base-300 rounded-box border border-base-300" role="tree" aria-label="{{ __( 'Categories' ) }}" data-category-tree>
            @foreach ( $tree as $row )
                @php $category = $row['category']; @endphp
                <li
                    wire:key="category-{{ $category->id }}"
                    role="treeitem"
                    aria-level="{{ $row['depth'] + 1 }}"
                    class="flex flex-wrap items-center gap-3 p-3"
                    style="padding-inline-start: {{ 0.75 + $row['depth'] * 1.5 }}rem"
                    data-category="{{ $category->id }}"
                    data-depth="{{ $row['depth'] }}"
                >
                    @if ( $row['depth'] > 0 )
                        <x-artisanpack-icon name="o-arrow-turn-down-right" class="size-4 opacity-50" aria-hidden="true" />
                    @endif

                    <div class="min-w-0 grow">
                        <span class="font-semibold">{{ $category->name }}</span>
                        <span class="ms-2 text-sm opacity-75">/{{ $category->slug }}</span>
                        @if ( $row['children'] > 0 )
                            <span class="block text-xs opacity-75">{{ trans_choice( ':count subcategory|:count subcategories', $row['children'], [ 'count' => $row['children'] ] ) }}</span>
                        @endif
                    </div>

                    <x-artisanpack-badge
                        :value="trans_choice( ':count product|:count products', (int) $category->products_count, [ 'count' => (int) $category->products_count ] )"
                        class="badge-ghost"
                        data-product-count="{{ (int) $category->products_count }}"
                    />

                    <div class="flex items-center gap-1">
                        @if ( $canUpdate )
                            <x-artisanpack-button
                                variant="ghost"
                                size="sm"
                                icon="o-arrow-up"
                                wire:click="move( {{ $category->id }}, -1 )"
                                :disabled="$row['first']"
                                :aria-label="__( 'Move :name up', [ 'name' => $category->name ] )"
                            />
                            <x-artisanpack-button
                                variant="ghost"
                                size="sm"
                                icon="o-arrow-down"
                                wire:click="move( {{ $category->id }}, 1 )"
                                :disabled="$row['last']"
                                :aria-label="__( 'Move :name down', [ 'name' => $category->name ] )"
                            />
                        @endif
                        @if ( $canCreate )
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-plus" wire:click="create( {{ $category->id }} )" :aria-label="__( 'Add a subcategory to :name', [ 'name' => $category->name ] )" />
                        @endif
                        @if ( $canUpdate )
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-pencil" wire:click="edit( {{ $category->id }} )" :aria-label="__( 'Edit :name', [ 'name' => $category->name ] )" />
                        @endif
                        @if ( $canDelete )
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="confirmDelete( {{ $category->id }} )" :aria-label="__( 'Delete :name', [ 'name' => $category->name ] )" />
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <x-artisanpack-drawer
        wire:model="editing"
        :title="null === $categoryId ? __( 'New category' ) : __( 'Edit category' )"
        right
        separator
        with-close-button
        close-on-escape
        class="w-full max-w-lg"
    >
        <form wire:submit="save" class="flex flex-col gap-4" data-category-form>
            @include( 'ecommerce-admin::partials.error-summary' )
            <x-artisanpack-input id="category-name" :label="__( 'Name' )" wire:model="form.name" required />
            <x-artisanpack-input id="category-slug" :label="__( 'Slug' )" :hint="__( 'Leave empty to make one from the name.' )" wire:model="form.slug" />
            <x-artisanpack-select
                id="category-parent"
                :label="__( 'Parent' )"
                :options="$parentOptions"
                :placeholder="__( 'None (top level)' )"
                placeholder-value=""
                :hint="null === $categoryId ? null : __( 'A category cannot sit under itself or one of its subcategories, so those are not listed.' )"
                wire:model="form.parent_id"
            />
            <x-artisanpack-textarea id="category-description" :label="__( 'Description' )" wire:model="form.description" rows="4" />
            <x-artisanpack-input id="category-icon" :label="__( 'Icon' )" :hint="__( 'An icon name, such as o-photo.' )" wire:model="form.icon" />

            <fieldset class="fieldset" data-category-image>
                <legend class="fieldset-legend">{{ __( 'Image' ) }}</legend>
                <div class="flex flex-wrap items-center gap-3">
                    @if ( null !== $imagePreview )
                        <img src="{{ $imagePreview }}" alt="" class="size-16 rounded object-cover" />
                    @elseif ( null !== $form['image_media_id'] && '' !== $form['image_media_id'] )
                        <span class="text-sm">{{ __( 'Media library file #:id', [ 'id' => $form['image_media_id'] ] ) }}</span>
                    @endif

                    @if ( $mediaLibrary )
                        <x-artisanpack-button
                            variant="outline"
                            size="sm"
                            icon="o-photo"
                            x-on:click="Livewire.dispatch( 'open-media-modal', { context: @js( Index::MEDIA_CONTEXT ) } )"
                            :label="empty( $form['image_media_id'] ) ? __( 'Choose image' ) : __( 'Replace image' )"
                        />
                    @else
                        <x-artisanpack-input id="category-image" type="number" min="1" :label="__( 'Media id' )" :hint="__( 'Install the media library to pick images.' )" wire:model="form.image_media_id" />
                    @endif

                    @unless ( empty( $form['image_media_id'] ) )
                        <x-artisanpack-button variant="ghost" size="sm" icon="o-x-mark" wire:click="clearImage" :label="__( 'Remove' )" />
                    @endunless
                </div>
            </fieldset>

            <div class="flex justify-end gap-2">
                <x-artisanpack-button variant="ghost" wire:click="$set( 'editing', false )" :label="__( 'Cancel' )" />
                <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" :label="__( 'Save category' )" />
            </div>
        </form>
    </x-artisanpack-drawer>

    @if ( null !== $deleting )
        @php
            $deleteTitle   = __( 'Delete ":name"?', [ 'name' => $deleting->name ] );
            $targetChoices = [
                [ 'id' => 'parent', 'name' => null === $deletingParent ? __( 'Up to the top level' ) : __( 'Up to ":name"', [ 'name' => $deletingParent->name ] ) ],
                [ 'id' => 'other', 'name' => __( 'Under another category' ) ],
            ];
        @endphp
        <x-artisanpack-modal wire:model="confirmingDelete" :title="$deleteTitle" separator>
            <div class="flex flex-col gap-3" data-delete-category>
                <p>
                    {{ trans_choice( 'Its :count product stays in the catalog but leaves this category.|Its :count products stay in the catalog but leave this category.', (int) $deleting->products_count, [ 'count' => (int) $deleting->products_count ] ) }}
                </p>

                @if ( $deletingChildren > 0 )
                    <fieldset class="fieldset" data-children-choice>
                        <legend class="fieldset-legend">
                            {{ trans_choice( 'It has :count subcategory. Where should it go?|It has :count subcategories. Where should they go?', $deletingChildren, [ 'count' => $deletingChildren ] ) }}
                        </legend>
                        <x-artisanpack-radio
                            id="category-children-target"
                            :options="$targetChoices"
                            wire:model.live="childrenTarget"
                        />
                        @if ( 'other' === $childrenTarget )
                            <x-artisanpack-select
                                id="category-children-target-id"
                                :label="__( 'New parent' )"
                                :options="$targetOptions"
                                :placeholder="__( 'Choose a category' )"
                                placeholder-value=""
                                wire:model="childrenTargetId"
                            />
                        @endif
                    </fieldset>
                @endif
            </div>

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" wire:click="cancelDelete" :label="__( 'Keep category' )" />
                <x-artisanpack-button
                    color="error"
                    wire:click="delete( {{ \Illuminate\Support\Js::from( $deleteToken ) }} )"
                    wire:loading.attr="disabled"
                    :label="__( 'Delete category' )"
                />
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif

    @if ( $mediaLibrary )
        <livewire:dynamic-component :is="\ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia::modal()" :multi-select="false" key="category-media-modal" />
    @endif
</div>
