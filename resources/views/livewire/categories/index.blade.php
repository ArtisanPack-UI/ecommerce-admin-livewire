{{--
    Categories (#122). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Categories\Index.

    The category tree in one card. Each row shows its level by indentation,
    its subcategory and product counts, and icon buttons to move it up or
    down, add a subcategory, edit or delete it, each named for its category.
    Order in the tree is the storefront order.

    The tree follows the ARIA tree pattern: one tab stop on the current row,
    arrow keys to move between rows (Right to the first subcategory, Left to
    the parent, mirrored in right-to-left layouts), Home and End. The current
    row's buttons follow it in the tab order. The roving state is inline
    Alpine, so no script needs a CSP nonce.

    Editing opens in a drawer. Deleting asks in a dialog; a category with
    subcategories asks where they go. Products are never deleted.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Categories\Index;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use Illuminate\Support\Js;
@endphp
<div
    class="ec-stack"
    data-categories-index
    x-data="{
        focusPage() {
            const target = this.$root.querySelector( '[data-new-category]' ) ?? this.$root.querySelector( 'h1' );

            if ( target && 'H1' === target.tagName ) {
                target.setAttribute( 'tabindex', '-1' );
            }

            target?.focus();
        },
    }"
    {{-- With the last category gone the tree (and its own listener) is gone too; focus New category, or the heading for a user who can't create. --}}
    x-on:ecommerce-admin-category-deleted.window="null === $event.detail.focus && $nextTick( () => focusPage() )"
>
    <x-artisanpack-page-header :title="__( 'Categories' )" :description="__( 'Group products so shoppers can browse them. Order here is the order on the storefront.' )" :level="1">
        @if ( $canCreate )
            <x-slot:actions>
                <x-artisanpack-button size="sm" leading-icon="plus" wire:click="create" data-new-category>{{ __( 'New category' ) }}</x-artisanpack-button>
            </x-slot:actions>
        @endif
    </x-artisanpack-page-header>

    @if ( 0 === $total )
        <div class="apui-card">
            <x-artisanpack-ec-admin-empty-state
                icon="folder"
                :heading-level="2"
                :title="__( 'No categories yet' )"
                :description="__( 'Group products into categories so shoppers can browse them.' )"
            >
                @if ( $canCreate )
                    <x-artisanpack-button leading-icon="plus" wire:click="create">{{ __( 'New category' ) }}</x-artisanpack-button>
                @endif
            </x-artisanpack-ec-admin-empty-state>
        </div>
    @else
        <div class="apui-card ec-category-card">
            <ul
                role="tree"
                class="ec-plain-list"
                aria-label="{{ __( 'Categories' ) }}"
                data-category-tree
                data-tree-ids="{{ collect( $tree )->map( static fn ( array $row ): int => (int) $row['category']->id )->implode( ',' ) }}"
                x-data="{
                    active: null,
                    items() {
                        return Array.from( this.$root.querySelectorAll( '[role=treeitem]' ) );
                    },
                    isTabStop( id, first ) {
                        const ids = ( this.$root.dataset.treeIds || '' ).split( ',' ).map( Number );

                        return ids.includes( this.active ) ? this.active === id : first;
                    },
                    focusItem( item ) {
                        if ( ! item ) {
                            return;
                        }

                        this.active = Number( item.dataset.category );
                        item.focus();
                    },
                    keydown( event ) {
                        if ( 'treeitem' !== event.target.getAttribute( 'role' ) ) {
                            return;
                        }

                        const items = this.items();
                        const index = items.indexOf( event.target );
                        const level = Number( event.target.getAttribute( 'aria-level' ) );
                        const rtl   = 'rtl' === getComputedStyle( this.$root ).direction;
                        let key     = event.key;

                        if ( rtl && 'ArrowLeft' === key ) {
                            key = 'ArrowRight';
                        } else if ( rtl && 'ArrowRight' === key ) {
                            key = 'ArrowLeft';
                        }

                        let target = null;

                        if ( 'ArrowDown' === key ) {
                            target = items[ index + 1 ];
                        } else if ( 'ArrowUp' === key ) {
                            target = items[ index - 1 ];
                        } else if ( 'Home' === key ) {
                            target = items[0];
                        } else if ( 'End' === key ) {
                            target = items[ items.length - 1 ];
                        } else if ( 'ArrowRight' === key ) {
                            const next = items[ index + 1 ];
                            target     = next && Number( next.getAttribute( 'aria-level' ) ) === level + 1 ? next : null;
                        } else if ( 'ArrowLeft' === key ) {
                            target = items.slice( 0, index ).reverse().find( ( item ) => Number( item.getAttribute( 'aria-level' ) ) === level - 1 ) ?? null;
                        } else {
                            return;
                        }

                        event.preventDefault();
                        this.focusItem( target );
                    },
                }"
                x-on:keydown="keydown( $event )"
                x-on:ecommerce-admin-category-deleted.window="$nextTick( () => focusItem( items().find( ( item ) => Number( item.dataset.category ) === $event.detail.focus ) ?? items()[0] ) )"
                x-on:focusin="const item = $event.target.closest( '[role=treeitem]' ); if ( item ) { active = Number( item.dataset.category ); }"
            >
                @foreach ( $tree as $row )
                    @php
                        $category = $row['category'];
                        $id       = (int) $category->id;
                        $tabStop  = 'isTabStop( ' . $id . ', ' . ( $loop->first ? 'true' : 'false' ) . ' ) ? 0 : -1';
                        $labelled = 'category-' . $id . '-name' . ( $row['children'] > 0 ? ' category-' . $id . '-children' : '' ) . ' category-' . $id . '-products';
                    @endphp
                    <li
                        wire:key="category-{{ $id }}"
                        role="treeitem"
                        class="ec-category-row"
                        aria-level="{{ $row['depth'] + 1 }}"
                        aria-posinset="{{ $row['position'] }}"
                        aria-setsize="{{ $row['siblings'] }}"
                        aria-labelledby="{{ $labelled }}"
                        @if ( $row['children'] > 0 ) aria-expanded="true" @endif
                        style="--ec-category-depth: {{ min( $row['depth'], 8 ) }}"
                        x-bind:tabindex="{{ $tabStop }}"
                        data-category="{{ $id }}"
                        data-depth="{{ $row['depth'] }}"
                    >
                        <x-artisanpack-icon name="folder" class="ec-category-icon" aria-hidden="true" />
                        <span id="category-{{ $id }}-name" class="ec-category-name">{{ $category->name }}</span>
                        @if ( $row['children'] > 0 )
                            <span id="category-{{ $id }}-children" class="ec-muted" data-subcategory-count="{{ $row['children'] }}">{{ trans_choice( ':count subcategory|:count subcategories', $row['children'], [ 'count' => $row['children'] ] ) }}</span>
                        @endif
                        <x-artisanpack-badge
                            id="category-{{ $id }}-products"
                            color="neutral"
                            size="sm"
                            :value="trans_choice( ':count product|:count products', (int) $category->products_count, [ 'count' => (int) $category->products_count ] )"
                            data-product-count="{{ (int) $category->products_count }}"
                        />

                        @if ( $canUpdate || $canCreate || $canDelete )
                            <span class="ec-category-actions">
                                @if ( $canUpdate )
                                    <x-artisanpack-button
                                        variant="ghost"
                                        color="neutral"
                                        size="xs"
                                        icon-only
                                        leading-icon="arrow-up"
                                        x-bind:tabindex="{{ $tabStop }}"
                                        wire:click="move( {{ $id }}, -1 )" wire:loading.attr="disabled"
                                        data-reorder="up"
                                        data-reorder-list="categories-{{ $category->parent_id ?? 'root' }}"
                                        data-reorder-key="category-{{ $id }}"
                                        data-reorder-item="{{ $category->name }}"
                                        :disabled="$row['first']"
                                        :aria-label="__( 'Move :name up', [ 'name' => $category->name ] )"
                                    />
                                    <x-artisanpack-button
                                        variant="ghost"
                                        color="neutral"
                                        size="xs"
                                        icon-only
                                        leading-icon="arrow-down"
                                        x-bind:tabindex="{{ $tabStop }}"
                                        wire:click="move( {{ $id }}, 1 )" wire:loading.attr="disabled"
                                        data-reorder="down"
                                        data-reorder-list="categories-{{ $category->parent_id ?? 'root' }}"
                                        data-reorder-key="category-{{ $id }}"
                                        data-reorder-item="{{ $category->name }}"
                                        :disabled="$row['last']"
                                        :aria-label="__( 'Move :name down', [ 'name' => $category->name ] )"
                                    />
                                @endif
                                @if ( $canCreate )
                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="plus" x-bind:tabindex="{{ $tabStop }}" wire:click="create( {{ $id }} )" :aria-label="__( 'Add a subcategory to :name', [ 'name' => $category->name ] )" />
                                @endif
                                @if ( $canUpdate )
                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="pencil" x-bind:tabindex="{{ $tabStop }}" wire:click="edit( {{ $id }} )" :aria-label="__( 'Edit :name', [ 'name' => $category->name ] )" />
                                @endif
                                @if ( $canDelete )
                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" x-bind:tabindex="{{ $tabStop }}" wire:click="confirmDelete( {{ $id }} )" :aria-label="__( 'Delete :name', [ 'name' => $category->name ] )" />
                                @endif
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-artisanpack-drawer
        wire:model="editing"
        :title="null === $categoryId ? __( 'New category' ) : __( 'Edit category' )"
        side="end"
        size="md"
    >
        <form id="category-form" wire:submit="save" class="ec-form-stack" data-category-form>
            @include( 'ecommerce-admin::partials.error-summary' )
            <x-artisanpack-input id="category-name" :label="__( 'Name' )" wire:model="form.name" required />
            <x-artisanpack-input id="category-slug" class="ec-mono-input" dir="ltr" :label="__( 'Slug' )" :hint="__( 'Leave empty to make one from the name.' )" wire:model="form.slug" />
            <x-artisanpack-select
                id="category-parent"
                :label="__( 'Parent' )"
                :options="ControlOptions::from( $parentOptions )"
                :placeholder="__( 'None (top level)' )"
                :hint="null === $categoryId ? null : __( 'A category cannot sit under itself or one of its subcategories, so those are not listed.' )"
                wire:model="form.parent_id"
            />
            <x-artisanpack-textarea id="category-description" :label="__( 'Description' )" wire:model="form.description" rows="3" />
            <x-artisanpack-input id="category-icon" class="ec-mono-input" dir="ltr" :label="__( 'Icon' )" :hint="__( 'An icon name, such as o-photo.' )" wire:model="form.icon" />

            <fieldset class="ec-fieldset" data-category-image>
                <legend class="ec-fieldset__legend">{{ __( 'Image' ) }}</legend>
                <div class="ec-category-image">
                    @if ( null !== $imagePreview )
                        <img src="{{ $imagePreview }}" alt="" class="ec-category-thumb" />
                    @endif
                    @unless ( empty( $form['image_media_id'] ) )
                        <span class="ec-muted">{{ __( 'Media library file #:id', [ 'id' => $form['image_media_id'] ] ) }}</span>
                    @endunless

                    @if ( $mediaLibrary )
                        <x-artisanpack-button
                            variant="outline"
                            color="neutral"
                            size="xs"
                            leading-icon="image"
                            x-on:click="Livewire.dispatch( 'open-media-modal', { context: {{ Js::from( Index::MEDIA_CONTEXT ) }} } )">{{ empty( $form['image_media_id'] ) ? __( 'Choose image' ) : __( 'Replace image' ) }}</x-artisanpack-button>
                    @else
                        <x-artisanpack-input id="category-image" type="number" min="1" :label="__( 'Media id' )" :hint="__( 'Install the media library to pick images.' )" wire:model="form.image_media_id" />
                    @endif

                    @unless ( empty( $form['image_media_id'] ) )
                        <x-artisanpack-button variant="ghost" color="neutral" size="xs" wire:click="clearImage">{{ __( 'Remove' ) }}</x-artisanpack-button>
                    @endunless
                </div>
            </fieldset>
        </form>

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" color="neutral" wire:click="$set( 'editing', false )">{{ __( 'Cancel' ) }}</x-artisanpack-button>
            <x-artisanpack-button type="submit" form="category-form" wire:loading.attr="disabled">{{ __( 'Save category' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-drawer>

    @if ( null !== $deleting )
        @php
            $deleteTitle   = __( 'Delete ":name"?', [ 'name' => $deleting->name ] );
            $targetChoices = null === $deletingParent
                ? [ [ 'id' => 'parent', 'name' => __( 'Up to the top level' ) ] ]
                : [
                    [ 'id' => 'top', 'name' => __( 'Up to the top level' ) ],
                    [ 'id' => 'parent', 'name' => __( 'Up to ":name"', [ 'name' => $deletingParent->name ] ) ],
                ];
            $targetChoices[] = [ 'id' => 'other', 'name' => __( 'Under another category' ) ];
        @endphp
        <x-artisanpack-dialog
            wire:model="confirmingDelete"
            role="alertdialog"
            color="error"
            icon="trash"
            :title="$deleteTitle"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided', 'body' => 'ec-stack' ]"
        >
            <div class="ec-stack" data-delete-category="{{ $deleting->id }}">
                <ul class="ec-bullets">
                    <li>{{ trans_choice( 'Its :count product stays in the catalog but leaves this category.|Its :count products stay in the catalog but leave this category.', (int) $deleting->products_count, [ 'count' => (int) $deleting->products_count ] ) }}</li>
                </ul>

                @if ( $deletingChildren > 0 )
                    <div class="ec-form-stack" data-children-choice>
                        <x-artisanpack-radio-group
                            id="category-children-target"
                            :label="trans_choice( 'It has :count subcategory. Where should it go?|It has :count subcategories. Where should they go?', $deletingChildren, [ 'count' => $deletingChildren ] )"
                            :options="ControlOptions::from( $targetChoices )"
                            wire:model.live="childrenTarget"
                        />
                        <x-artisanpack-select
                            id="category-children-target-id"
                            :label="__( 'New parent' )"
                            :options="ControlOptions::from( $targetOptions )"
                            :placeholder="__( 'Choose a category' )"
                            :disabled="'other' !== $childrenTarget"
                            wire:model="childrenTargetId"
                        />
                    </div>
                @endif
            </div>

            <x-slot:footer>
                <x-artisanpack-button variant="outline" color="neutral" wire:click="cancelDelete">{{ __( 'Keep category' ) }}</x-artisanpack-button>
                <x-artisanpack-button
                    color="error"
                    wire:click="delete( {{ Js::from( $deleteToken ) }} )"
                    wire:loading.attr="disabled"
                    data-delete-button>{{ __( 'Delete category' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif

    @if ( $mediaLibrary )
        <livewire:dynamic-component :is="\ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia::modal()" :multi-select="false" key="category-media-modal" />
    @endif
</div>
