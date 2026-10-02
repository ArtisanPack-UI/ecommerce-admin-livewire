<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceAdminLivewire\EcommerceAdminLivewireServiceProvider;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Authorization matrix (spec §6, §12)
|--------------------------------------------------------------------------
|
| MATRIX declares, for every Livewire component the package registers, the
| ability its mount() needs and, for every public action, the ability the
| action needs and the arguments to call it with. A null ability means
| "may enter the admin" (at least one visible screen).
|
| The suite then proves each declaration both ways:
|
| - a user who can enter the admin but lacks the ability is refused, so an
|   action that only checks admin access fails here;
| - a user holding just that ability gets through, so the refusal really
|   comes from the ability check.
|
| A component or action missing from MATRIX fails the suite, so a new screen
| cannot ship without declaring (and enforcing) its authorization.
|
*/

const MATRIX = [
    'artisanpack-ecommerce-admin-dashboard' => [
        'mount'   => [ 'params' => [], 'ability' => null ],
        'actions' => [
            'orderBroadcast'  => [ 'args' => [], 'ability' => null ],
            'stockBroadcast'  => [ 'args' => [], 'ability' => null ],
            'reviewBroadcast' => [ 'args' => [], 'ability' => null ],
        ],
    ],
    'artisanpack-ecommerce-admin-navigation' => [
        'mount'   => [ 'params' => [], 'ability' => null ],
        'actions' => [
            'refreshBadges' => [ 'args' => [], 'ability' => null ],
        ],
    ],

    'artisanpack-ecommerce-admin-orders-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'order.viewAny' ],
        'actions' => [
            'sort'                         => [ 'args' => [ 'placed' ], 'ability' => 'order.viewAny' ],
            'resetFilters'                 => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'selectAllMatchingRows'        => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'clearSelection'               => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'runBulkAction'                => [ 'args' => [ 'export' ], 'ability' => 'order.viewAny' ],
            'confirmBulkAction'            => [ 'args' => [ 'token' ], 'ability' => 'order.viewAny' ],
            'cancelBulkAction'             => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'exportCsv'                    => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'getTableExportData'           => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'order.viewAny' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'order.viewAny' ],
            'orderBroadcast'               => [ 'args' => [], 'ability' => 'order.viewAny' ],
            'dismissNewOrders'             => [ 'args' => [], 'ability' => 'order.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-orders-show' => [
        'mount'   => [ 'params' => [ 'order' => '@order' ], 'ability' => 'order.view' ],
        'actions' => [
            'addToBoard'      => [ 'args' => [], 'ability' => 'kanbanCard.move' ],
            'removeFromBoard' => [ 'args' => [ 1 ], 'ability' => 'kanbanCard.move' ],
            'orderUpdated'    => [ 'args' => [], 'ability' => 'order.view' ],
            'orderBroadcast'  => [ 'args' => [], 'ability' => 'order.view' ],
            'reloadOrder'     => [ 'args' => [], 'ability' => 'order.view' ],
        ],
    ],
    'artisanpack-ecommerce-admin-order-status' => [
        'mount'   => [ 'params' => [ 'order' => '@order' ], 'ability' => 'order.view' ],
        'actions' => [
            'changeStatus'    => [ 'args' => [], 'ability' => 'order.update' ],
            'changeSubstatus' => [ 'args' => [], 'ability' => 'order.update' ],
            'cancelOrder'     => [ 'args' => [ 'token' ], 'ability' => 'order.cancel' ],
        ],
    ],
    'artisanpack-ecommerce-admin-order-refunds' => [
        'mount'   => [ 'params' => [ 'order' => '@order' ], 'ability' => 'order.view' ],
        'actions' => [
            'startRefund' => [ 'args' => [], 'ability' => 'order.refund' ],
            'refund'      => [ 'args' => [ 'token' ], 'ability' => 'order.refund' ],
        ],
    ],
    'artisanpack-ecommerce-admin-order-fulfillment' => [
        'mount'   => [ 'params' => [ 'order' => '@order' ], 'ability' => 'order.view' ],
        'actions' => [
            'startShipment'  => [ 'args' => [], 'ability' => 'order.update' ],
            'createShipment' => [ 'args' => [ 'token' ], 'ability' => 'order.update' ],
            'editTracking'   => [ 'args' => [ 1 ], 'ability' => 'order.update' ],
            'updateTracking' => [ 'args' => [], 'ability' => 'order.update' ],
            'buyLabel'       => [ 'args' => [ 1, 'token' ], 'ability' => 'order.update' ],
        ],
    ],
    'artisanpack-ecommerce-admin-order-edits' => [
        'mount'   => [ 'params' => [ 'order' => '@order' ], 'ability' => 'order.view' ],
        'actions' => [
            'startEdit'        => [ 'args' => [], 'ability' => 'order.update' ],
            'addItem'          => [ 'args' => [], 'ability' => 'order.update' ],
            'removeNewItem'    => [ 'args' => [ 0 ], 'ability' => 'order.update' ],
            'previewEdit'      => [ 'args' => [], 'ability' => 'order.update' ],
            'applyEdit'        => [ 'args' => [ 'token' ], 'ability' => 'order.update' ],
            'rollbackEdit'     => [ 'args' => [ 1, 'token' ], 'ability' => 'order.update' ],
            // The page re-authorizes order.view on every request; the picker source then needs product.viewAny.
            'searchPicker'     => [ 'args' => [ 'a', 'product', 'addProductId' ], 'ability' => 'product.viewAny' ],
            'optionsForPicker' => [ 'args' => [ 'product', 'addProductId' ], 'ability' => 'product.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-timeline' => [
        'mount'   => [ 'params' => [ 'subject' => '@order-model' ], 'ability' => 'order.view' ],
        'actions' => [
            'loadMore' => [ 'args' => [], 'ability' => 'order.view' ],
        ],
    ],

    'artisanpack-ecommerce-admin-notes' => [
        'mount'   => [ 'params' => [ 'subject' => '@order-model' ], 'ability' => 'order.view' ],
        'actions' => [
            'addNote'    => [ 'args' => [], 'ability' => 'order.update' ],
            // Deleting needs authorship or order.update on an existing note; a missing note only needs the view check.
            'deleteNote' => [ 'args' => [ 1 ], 'ability' => 'order.view' ],
        ],
    ],

    'artisanpack-ecommerce-admin-products-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'product.viewAny' ],
        'actions' => [
            'sort'                         => [ 'args' => [ 'name' ], 'ability' => 'product.viewAny' ],
            'resetFilters'                 => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'selectAllMatchingRows'        => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'clearSelection'               => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'runBulkAction'                => [ 'args' => [ 'export' ], 'ability' => 'product.viewAny' ],
            'confirmBulkAction'            => [ 'args' => [ 'token' ], 'ability' => 'product.viewAny' ],
            'cancelBulkAction'             => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'exportCsv'                    => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'getTableExportData'           => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'product.viewAny' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'product.viewAny' ],
            'exportCatalog'                => [ 'args' => [], 'ability' => 'product.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-products-form' => [
        'mount'   => [ 'params' => [], 'ability' => 'product.create' ],
        'actions' => [
            'addScheduledPrice'  => [ 'args' => [], 'ability' => 'product.create' ],
            'removePrice'        => [ 'args' => [ 0 ], 'ability' => 'product.create' ],
            'addGalleryUrl'      => [ 'args' => [], 'ability' => 'product.create' ],
            'removeGalleryImage' => [ 'args' => [ 0 ], 'ability' => 'product.create' ],
            'moveGalleryImage'   => [ 'args' => [ 0, 1 ], 'ability' => 'product.create' ],
            'clearFeaturedImage' => [ 'args' => [], 'ability' => 'product.create' ],
            'mediaSelected'      => [ 'args' => [ [], 'other' ], 'ability' => 'product.create' ],
            'save'               => [ 'args' => [], 'ability' => 'product.create' ],
            // The form re-authorizes product.create on every request; the picker source then needs product.viewAny.
            'searchPicker'       => [ 'args' => [ 'a', 'category', 'categoryIds' ], 'ability' => 'product.viewAny' ],
            'optionsForPicker'   => [ 'args' => [ 'category', 'categoryIds' ], 'ability' => 'product.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-product-variable-panel' => [
        'mount'   => [ 'params' => [ 'productId' => null ], 'ability' => 'product.create' ],
        'actions' => [
            'addAttribute'      => [ 'args' => [], 'ability' => 'product.create' ],
            'removeAttribute'   => [ 'args' => [ 0 ], 'ability' => 'product.create' ],
            'moveAttribute'     => [ 'args' => [ 0, 1 ], 'ability' => 'product.create' ],
            'addValue'          => [ 'args' => [ 0 ], 'ability' => 'product.create' ],
            'removeValue'       => [ 'args' => [ 0, 0 ], 'ability' => 'product.create' ],
            'moveValue'         => [ 'args' => [ 0, 0, 1 ], 'ability' => 'product.create' ],
            'generateVariants'  => [ 'args' => [], 'ability' => 'product.create' ],
            'confirmGenerate'   => [ 'args' => [], 'ability' => 'product.create' ],
            'cancelGenerate'    => [ 'args' => [], 'ability' => 'product.create' ],
            'addVariant'        => [ 'args' => [], 'ability' => 'product.create' ],
            'removeVariant'     => [ 'args' => [ 0 ], 'ability' => 'product.create' ],
            'moveVariant'       => [ 'args' => [ 0, 1 ], 'ability' => 'product.create' ],
            'mediaSelected'     => [ 'args' => [ [], 'other' ], 'ability' => 'product.create' ],
            'productSaved'      => [ 'args' => [], 'ability' => 'product.create' ],
        ],
    ],
    'artisanpack-ecommerce-admin-product-digital-panel' => [
        'mount'   => [ 'params' => [ 'productId' => null ], 'ability' => 'product.create' ],
        'actions' => [
            'addFile'       => [ 'args' => [], 'ability' => 'product.create' ],
            'removeFile'    => [ 'args' => [ 0 ], 'ability' => 'product.create' ],
            'mediaSelected' => [ 'args' => [ [], 'other' ], 'ability' => 'product.create' ],
            'productSaved'  => [ 'args' => [], 'ability' => 'product.create' ],
        ],
    ],
    'artisanpack-ecommerce-admin-product-children-panel' => [
        'mount'   => [ 'params' => [ 'productId' => null ], 'ability' => 'product.create' ],
        'actions' => [
            'addChild'         => [ 'args' => [], 'ability' => 'product.create' ],
            'removeChild'      => [ 'args' => [ 0 ], 'ability' => 'product.create' ],
            'moveChild'        => [ 'args' => [ 0, 1 ], 'ability' => 'product.create' ],
            'productSaved'     => [ 'args' => [], 'ability' => 'product.create' ],
            // The panel re-authorizes product.create on every request; the picker source then needs product.viewAny.
            'searchPicker'     => [ 'args' => [ 'a', 'product', 'state.children.0.product_id' ], 'ability' => 'product.viewAny' ],
            'optionsForPicker' => [ 'args' => [ 'product', 'state.children.0.product_id' ], 'ability' => 'product.viewAny' ],
        ],
    ],

    'artisanpack-ecommerce-admin-products-import' => [
        'mount'   => [ 'params' => [], 'ability' => 'product.create' ],
        'actions' => [
            // Livewire's upload handlers: the screen check on every request covers them.
            '_startUpload'   => [ 'args' => [ 'csv', [ [ 'name' => 'a.csv', 'size' => 10, 'type' => 'text/csv' ] ], false ], 'ability' => 'product.create' ],
            '_finishUpload'  => [ 'args' => [ 'csv', [], true ], 'ability' => 'product.create' ],
            '_uploadErrored' => [ 'args' => [ 'csv', null, false ], 'ability' => 'product.create' ],
            '_removeUpload'  => [ 'args' => [ 'csv', 'missing.csv' ], 'ability' => 'product.create' ],
            'upload'         => [ 'args' => [], 'ability' => 'product.create' ],
            'check'          => [ 'args' => [], 'ability' => 'product.create' ],
            'editMapping'    => [ 'args' => [], 'ability' => 'product.create' ],
            'apply'          => [ 'args' => [ 'token' ], 'ability' => 'product.create' ],
            'resume'         => [ 'args' => [], 'ability' => 'product.create' ],
            'discard'        => [ 'args' => [], 'ability' => 'product.create' ],
            'downloadErrors' => [ 'args' => [], 'ability' => 'product.create' ],
            'downloadSample' => [ 'args' => [], 'ability' => 'product.create' ],
        ],
    ],
    'artisanpack-ecommerce-admin-categories-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'product.viewAny' ],
        'actions' => [
            'create'        => [ 'args' => [], 'ability' => 'product.create' ],
            'edit'          => [ 'args' => [ 1 ], 'ability' => 'product.update' ],
            // With no category open, saving creates one.
            'save'          => [ 'args' => [], 'ability' => 'product.create' ],
            'move'          => [ 'args' => [ 1, 1 ], 'ability' => 'product.update' ],
            'confirmDelete' => [ 'args' => [ 1 ], 'ability' => 'product.delete' ],
            'delete'        => [ 'args' => [ 'token' ], 'ability' => 'product.delete' ],
            'cancelDelete'  => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'clearImage'    => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'mediaSelected' => [ 'args' => [ [], 'other' ], 'ability' => 'product.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-tags-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'product.viewAny' ],
        'actions' => [
            'sort'                         => [ 'args' => [ 'name' ], 'ability' => 'product.viewAny' ],
            'resetFilters'                 => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'selectAllMatchingRows'        => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'clearSelection'               => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'runBulkAction'                => [ 'args' => [ 'export' ], 'ability' => 'product.viewAny' ],
            'confirmBulkAction'            => [ 'args' => [ 'token' ], 'ability' => 'product.viewAny' ],
            'cancelBulkAction'             => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'exportCsv'                    => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'getTableExportData'           => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'product.viewAny' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'product.viewAny' ],
            'createTag'                    => [ 'args' => [], 'ability' => 'product.create' ],
            'startRename'                  => [ 'args' => [ 1 ], 'ability' => 'product.update' ],
            'saveRename'                   => [ 'args' => [], 'ability' => 'product.update' ],
            'cancelRename'                 => [ 'args' => [], 'ability' => 'product.viewAny' ],
            'confirmDelete'                => [ 'args' => [ 1 ], 'ability' => 'product.delete' ],
        ],
    ],
    'artisanpack-ecommerce-admin-inventory-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'inventory.viewAny' ],
        'actions' => [
            'sort'                         => [ 'args' => [ 'available' ], 'ability' => 'inventory.viewAny' ],
            'resetFilters'                 => [ 'args' => [], 'ability' => 'inventory.viewAny' ],
            'selectAllMatchingRows'        => [ 'args' => [], 'ability' => 'inventory.viewAny' ],
            'clearSelection'               => [ 'args' => [], 'ability' => 'inventory.viewAny' ],
            'runBulkAction'                => [ 'args' => [ 'export' ], 'ability' => 'inventory.viewAny' ],
            'confirmBulkAction'            => [ 'args' => [ 'token' ], 'ability' => 'inventory.viewAny' ],
            'cancelBulkAction'             => [ 'args' => [], 'ability' => 'inventory.viewAny' ],
            'exportCsv'                    => [ 'args' => [], 'ability' => 'inventory.viewAny' ],
            'getTableExportData'           => [ 'args' => [], 'ability' => 'inventory.viewAny' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'inventory.viewAny' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'inventory.viewAny' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'inventory.viewAny' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'inventory.viewAny' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'inventory.viewAny' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'inventory.viewAny' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'inventory.viewAny' ],
            // Livewire's upload handlers: the screen check on every request covers them.
            '_startUpload'                 => [ 'args' => [ 'stockCsv', [ [ 'name' => 'a.csv', 'size' => 10, 'type' => 'text/csv' ] ], false ], 'ability' => 'inventory.viewAny' ],
            '_finishUpload'                => [ 'args' => [ 'stockCsv', [], true ], 'ability' => 'inventory.viewAny' ],
            '_uploadErrored'               => [ 'args' => [ 'stockCsv', null, false ], 'ability' => 'inventory.viewAny' ],
            '_removeUpload'                => [ 'args' => [ 'stockCsv', 'missing.csv' ], 'ability' => 'inventory.viewAny' ],
            'startAdjust'                  => [ 'args' => [ 1 ], 'ability' => 'inventory.adjust' ],
            'adjust'                       => [ 'args' => [ 'token' ], 'ability' => 'inventory.adjust' ],
            'closeAdjust'                  => [ 'args' => [], 'ability' => 'inventory.viewAny' ],
            'editThreshold'                => [ 'args' => [ 1 ], 'ability' => 'inventory.adjust' ],
            'saveThreshold'                => [ 'args' => [], 'ability' => 'inventory.adjust' ],
            'cancelThreshold'              => [ 'args' => [], 'ability' => 'inventory.viewAny' ],
            'toggleBackorder'              => [ 'args' => [ 1 ], 'ability' => 'inventory.adjust' ],
            'openBulkAdjust'               => [ 'args' => [], 'ability' => 'inventory.adjust' ],
            'dryRunBulkAdjust'             => [ 'args' => [], 'ability' => 'inventory.adjust' ],
            'applyBulkAdjust'              => [ 'args' => [ 'token' ], 'ability' => 'inventory.adjust' ],
        ],
    ],
    'artisanpack-ecommerce-admin-reviews-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'review.viewAny' ],
        'actions' => [
            'sort'                         => [ 'args' => [ 'submitted' ], 'ability' => 'review.viewAny' ],
            'resetFilters'                 => [ 'args' => [], 'ability' => 'review.viewAny' ],
            'selectAllMatchingRows'        => [ 'args' => [], 'ability' => 'review.viewAny' ],
            'clearSelection'               => [ 'args' => [], 'ability' => 'review.viewAny' ],
            'runBulkAction'                => [ 'args' => [ 'export' ], 'ability' => 'review.viewAny' ],
            'confirmBulkAction'            => [ 'args' => [ 'token' ], 'ability' => 'review.viewAny' ],
            'cancelBulkAction'             => [ 'args' => [], 'ability' => 'review.viewAny' ],
            'exportCsv'                    => [ 'args' => [], 'ability' => 'review.viewAny' ],
            'getTableExportData'           => [ 'args' => [], 'ability' => 'review.viewAny' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'review.viewAny' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'review.viewAny' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'review.viewAny' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'review.viewAny' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'review.viewAny' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'review.viewAny' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'review.viewAny' ],
            'showReview'                   => [ 'args' => [ 1 ], 'ability' => 'review.view' ],
            'moderate'                     => [ 'args' => [ 1, 'approve' ], 'ability' => 'review.moderate' ],
            'startReject'                  => [ 'args' => [ 1 ], 'ability' => 'review.moderate' ],
            'reject'                       => [ 'args' => [], 'ability' => 'review.moderate' ],
            'closeReject'                  => [ 'args' => [], 'ability' => 'review.viewAny' ],
            'confirmDelete'                => [ 'args' => [ 1 ], 'ability' => 'review.delete' ],
        ],
    ],
    'artisanpack-ecommerce-admin-digital-files-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'digitalFile.viewAny' ],
        'actions' => [
            'sort'                         => [ 'args' => [ 'label' ], 'ability' => 'digitalFile.viewAny' ],
            'resetFilters'                 => [ 'args' => [], 'ability' => 'digitalFile.viewAny' ],
            'selectAllMatchingRows'        => [ 'args' => [], 'ability' => 'digitalFile.viewAny' ],
            'clearSelection'               => [ 'args' => [], 'ability' => 'digitalFile.viewAny' ],
            'runBulkAction'                => [ 'args' => [ 'export' ], 'ability' => 'digitalFile.viewAny' ],
            'confirmBulkAction'            => [ 'args' => [ 'token' ], 'ability' => 'digitalFile.viewAny' ],
            'cancelBulkAction'             => [ 'args' => [], 'ability' => 'digitalFile.viewAny' ],
            'exportCsv'                    => [ 'args' => [], 'ability' => 'digitalFile.viewAny' ],
            'getTableExportData'           => [ 'args' => [], 'ability' => 'digitalFile.viewAny' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'digitalFile.viewAny' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'digitalFile.viewAny' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'digitalFile.viewAny' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'digitalFile.viewAny' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'digitalFile.viewAny' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'digitalFile.viewAny' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'digitalFile.viewAny' ],
            'create'                       => [ 'args' => [], 'ability' => 'digitalFile.create' ],
            'edit'                         => [ 'args' => [ 1 ], 'ability' => 'digitalFile.update' ],
            // With no file open, saving creates one.
            'save'                         => [ 'args' => [], 'ability' => 'digitalFile.create' ],
            'confirmDelete'                => [ 'args' => [ 1 ], 'ability' => 'digitalFile.delete' ],
            'mediaSelected'                => [ 'args' => [ [], 'other' ], 'ability' => 'digitalFile.viewAny' ],
            // The screen re-authorizes digitalFile.viewAny on every request; the picker source then needs product.viewAny.
            'searchPicker'                 => [ 'args' => [ 'a', 'product', 'form.product_id' ], 'ability' => 'product.viewAny' ],
            'optionsForPicker'             => [ 'args' => [ 'product', 'form.product_id' ], 'ability' => 'product.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-license-keys-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'licenseKey.view' ],
        'actions' => [
            'sort'                         => [ 'args' => [ 'issued' ], 'ability' => 'licenseKey.view' ],
            'resetFilters'                 => [ 'args' => [], 'ability' => 'licenseKey.view' ],
            'selectAllMatchingRows'        => [ 'args' => [], 'ability' => 'licenseKey.view' ],
            'clearSelection'               => [ 'args' => [], 'ability' => 'licenseKey.view' ],
            'runBulkAction'                => [ 'args' => [ 'export' ], 'ability' => 'licenseKey.view' ],
            'confirmBulkAction'            => [ 'args' => [ 'token' ], 'ability' => 'licenseKey.view' ],
            'cancelBulkAction'             => [ 'args' => [], 'ability' => 'licenseKey.view' ],
            'exportCsv'                    => [ 'args' => [], 'ability' => 'licenseKey.view' ],
            'getTableExportData'           => [ 'args' => [], 'ability' => 'licenseKey.view' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'licenseKey.view' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'licenseKey.view' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'licenseKey.view' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'licenseKey.view' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'licenseKey.view' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'licenseKey.view' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'licenseKey.view' ],
            'showActivations'              => [ 'args' => [ 1 ], 'ability' => 'licenseKey.view' ],
            'startRevoke'                  => [ 'args' => [ 1 ], 'ability' => 'licenseKey.revoke' ],
            'revoke'                       => [ 'args' => [ 'token' ], 'ability' => 'licenseKey.revoke' ],
            'closeRevoke'                  => [ 'args' => [], 'ability' => 'licenseKey.view' ],
        ],
    ],

    'artisanpack-ecommerce-admin-customers-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'customer.viewAny' ],
        'actions' => [
            'sort'                         => [ 'args' => [ 'spent' ], 'ability' => 'customer.viewAny' ],
            'resetFilters'                 => [ 'args' => [], 'ability' => 'customer.viewAny' ],
            'selectAllMatchingRows'        => [ 'args' => [], 'ability' => 'customer.viewAny' ],
            'clearSelection'               => [ 'args' => [], 'ability' => 'customer.viewAny' ],
            'runBulkAction'                => [ 'args' => [ 'export' ], 'ability' => 'customer.viewAny' ],
            'confirmBulkAction'            => [ 'args' => [ 'token' ], 'ability' => 'customer.viewAny' ],
            'cancelBulkAction'             => [ 'args' => [], 'ability' => 'customer.viewAny' ],
            'exportCsv'                    => [ 'args' => [], 'ability' => 'customer.viewAny' ],
            'getTableExportData'           => [ 'args' => [], 'ability' => 'customer.viewAny' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'customer.viewAny' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'customer.viewAny' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'customer.viewAny' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'customer.viewAny' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'customer.viewAny' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'customer.viewAny' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'customer.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-customers-show' => [
        'mount'   => [ 'params' => [ 'customer' => '@customer' ], 'ability' => 'customer.view' ],
        'actions' => [
            'customerChanged' => [ 'args' => [], 'ability' => 'customer.view' ],
            'startEdit'       => [ 'args' => [], 'ability' => 'customer.update' ],
            'cancelEdit'      => [ 'args' => [], 'ability' => 'customer.view' ],
            'saveDetails'     => [ 'args' => [], 'ability' => 'customer.update' ],
            'startDelete'     => [ 'args' => [], 'ability' => 'customer.delete' ],
            'cancelDelete'    => [ 'args' => [], 'ability' => 'customer.view' ],
            'deleteCustomer'  => [ 'args' => [ 'token' ], 'ability' => 'customer.delete' ],
        ],
    ],
    'artisanpack-ecommerce-admin-customer-orders' => [
        'mount'   => [ 'params' => [ 'customer' => '@customer-model' ], 'ability' => 'customer.view' ],
        'actions' => [
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'customer.view' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'customer.view' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'customer.view' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'customer.view' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'customer.view' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'customer.view' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'customer.view' ],
        ],
    ],
    'artisanpack-ecommerce-admin-customer-addresses' => [
        'mount'   => [ 'params' => [ 'customer' => '@customer-model' ], 'ability' => 'customer.view' ],
        'actions' => [
            'startAdd'      => [ 'args' => [], 'ability' => 'customer.update' ],
            'startEdit'     => [ 'args' => [ 1 ], 'ability' => 'customer.update' ],
            'cancelForm'    => [ 'args' => [], 'ability' => 'customer.view' ],
            'saveAddress'   => [ 'args' => [], 'ability' => 'customer.update' ],
            'makeDefault'   => [ 'args' => [ 1, 'shipping' ], 'ability' => 'customer.update' ],
            'confirmDelete' => [ 'args' => [ 1 ], 'ability' => 'customer.update' ],
            'cancelDelete'  => [ 'args' => [], 'ability' => 'customer.view' ],
            'deleteAddress' => [ 'args' => [ 'token' ], 'ability' => 'customer.update' ],
        ],
    ],
    'artisanpack-ecommerce-admin-customer-preferences' => [
        'mount'   => [ 'params' => [ 'customer' => '@customer-model' ], 'ability' => 'customer.view' ],
        'actions' => [
            'savePreferences' => [ 'args' => [], 'ability' => 'customer.update' ],
        ],
    ],

    'artisanpack-ecommerce-admin-promotions-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'promotion.viewAny' ],
        'actions' => [
            'sort'                         => [ 'args' => [ 'priority' ], 'ability' => 'promotion.viewAny' ],
            'resetFilters'                 => [ 'args' => [], 'ability' => 'promotion.viewAny' ],
            'selectAllMatchingRows'        => [ 'args' => [], 'ability' => 'promotion.viewAny' ],
            'clearSelection'               => [ 'args' => [], 'ability' => 'promotion.viewAny' ],
            'runBulkAction'                => [ 'args' => [ 'export' ], 'ability' => 'promotion.viewAny' ],
            'confirmBulkAction'            => [ 'args' => [ 'token' ], 'ability' => 'promotion.viewAny' ],
            'cancelBulkAction'             => [ 'args' => [], 'ability' => 'promotion.viewAny' ],
            'exportCsv'                    => [ 'args' => [], 'ability' => 'promotion.viewAny' ],
            'getTableExportData'           => [ 'args' => [], 'ability' => 'promotion.viewAny' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'promotion.viewAny' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'promotion.viewAny' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'promotion.viewAny' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'promotion.viewAny' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'promotion.viewAny' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'promotion.viewAny' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'promotion.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-promotions-form' => [
        'mount'   => [ 'params' => [], 'ability' => 'promotion.create' ],
        'actions' => [
            'save'             => [ 'args' => [], 'ability' => 'promotion.create' ],
            'addRule'          => [ 'args' => [ 'actions' ], 'ability' => 'promotion.create' ],
            'removeRule'       => [ 'args' => [ 'actions', 0 ], 'ability' => 'promotion.create' ],
            'moveRule'         => [ 'args' => [ 'actions', 0, 1 ], 'ability' => 'promotion.create' ],
            'reorderRules'     => [ 'args' => [ 'actions', [] ], 'ability' => 'promotion.create' ],
            'toggleRule'       => [ 'args' => [ 'actions', 'missing' ], 'ability' => 'promotion.create' ],
            // The form re-authorizes promotion.create on every request; config rows then need admin access, which promotion.viewAny gives.
            'addConfigRow'     => [ 'args' => [ 'promotion-action', 'tiered-discount', 'ruleRows.actions.0.config', 'tiers' ], 'ability' => 'promotion.viewAny' ],
            'removeConfigRow'  => [ 'args' => [ 'promotion-action', 'tiered-discount', 'ruleRows.actions.0.config', 'tiers', 0 ], 'ability' => 'promotion.viewAny' ],
            // The picker source then needs product.viewAny.
            'searchPicker'     => [ 'args' => [ 'a', 'product', 'ruleRows.actions.0.config.product_ids' ], 'ability' => 'product.viewAny' ],
            'optionsForPicker' => [ 'args' => [ 'product', 'ruleRows.actions.0.config.product_ids' ], 'ability' => 'product.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-promotion-coupons' => [
        'mount'   => [ 'params' => [ 'promotion' => '@promotion-model' ], 'ability' => 'promotion.view' ],
        'actions' => [
            'addCode'                      => [ 'args' => [], 'ability' => 'coupon.create' ],
            'startRename'                  => [ 'args' => [ 1 ], 'ability' => 'coupon.update' ],
            'saveRename'                   => [ 'args' => [], 'ability' => 'coupon.update' ],
            'cancelRename'                 => [ 'args' => [], 'ability' => 'promotion.view' ],
            'confirmDelete'                => [ 'args' => [ 1 ], 'ability' => 'coupon.delete' ],
            'cancelDelete'                 => [ 'args' => [], 'ability' => 'promotion.view' ],
            'deleteCode'                   => [ 'args' => [ 'token' ], 'ability' => 'coupon.delete' ],
            'generateCodes'                => [ 'args' => [], 'ability' => 'coupon.create' ],
            'exportCodes'                  => [ 'args' => [], 'ability' => 'promotion.view' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'promotion.view' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'promotion.view' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'promotion.view' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'promotion.view' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'promotion.view' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'promotion.view' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'promotion.view' ],
        ],
    ],
    'artisanpack-ecommerce-admin-promotion-usage' => [
        'mount'   => [ 'params' => [ 'promotion' => '@promotion-model' ], 'ability' => 'promotion.view' ],
        'actions' => [
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'promotion.view' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'promotion.view' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'promotion.view' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'promotion.view' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'promotion.view' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'promotion.view' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'promotion.view' ],
        ],
    ],
    'artisanpack-ecommerce-admin-shipping-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'shippingZone.viewAny' ],
        'actions' => [
            'toggleZone'          => [ 'args' => [ 1 ], 'ability' => 'shippingZone.viewAny' ],
            'createZone'          => [ 'args' => [], 'ability' => 'shippingZone.create' ],
            'editZone'            => [ 'args' => [ 1 ], 'ability' => 'shippingZone.update' ],
            // With no zone open, saving creates one.
            'saveZone'            => [ 'args' => [], 'ability' => 'shippingZone.create' ],
            'createMethod'        => [ 'args' => [ 1 ], 'ability' => 'shippingZone.update' ],
            'editMethod'          => [ 'args' => [ 1 ], 'ability' => 'shippingZone.update' ],
            'saveMethod'          => [ 'args' => [], 'ability' => 'shippingZone.update' ],
            'moveMethod'          => [ 'args' => [ 1, 1 ], 'ability' => 'shippingZone.update' ],
            'confirmDeleteZone'   => [ 'args' => [ 1 ], 'ability' => 'shippingZone.delete' ],
            'confirmDeleteMethod' => [ 'args' => [ 1 ], 'ability' => 'shippingZone.update' ],
            'cancelDelete'        => [ 'args' => [], 'ability' => 'shippingZone.viewAny' ],
            'delete'              => [ 'args' => [ 'token' ], 'ability' => 'shippingZone.delete' ],
            'addConfigRow'        => [ 'args' => [ 'shipping-method', 'price-based', 'methodForm.config', 'tiers' ], 'ability' => null ],
            'removeConfigRow'     => [ 'args' => [ 'shipping-method', 'price-based', 'methodForm.config', 'tiers', 0 ], 'ability' => null ],
        ],
    ],
    'artisanpack-ecommerce-admin-tax-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'taxRate.viewAny' ],
        'actions' => [
            'sort'                         => [ 'args' => [ 'country' ], 'ability' => 'taxRate.viewAny' ],
            'resetFilters'                 => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'selectAllMatchingRows'        => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'clearSelection'               => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'runBulkAction'                => [ 'args' => [ 'export' ], 'ability' => 'taxRate.viewAny' ],
            'confirmBulkAction'            => [ 'args' => [ 'token' ], 'ability' => 'taxRate.viewAny' ],
            'cancelBulkAction'             => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'exportCsv'                    => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'getTableExportData'           => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'taxRate.viewAny' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'taxRate.viewAny' ],
            // Livewire's upload handlers: the screen check on every request covers them.
            '_startUpload'                 => [ 'args' => [ 'ratesCsv', [ [ 'name' => 'a.csv', 'size' => 10, 'type' => 'text/csv' ] ], false ], 'ability' => 'taxRate.viewAny' ],
            '_finishUpload'                => [ 'args' => [ 'ratesCsv', [], true ], 'ability' => 'taxRate.viewAny' ],
            '_uploadErrored'               => [ 'args' => [ 'ratesCsv', null, false ], 'ability' => 'taxRate.viewAny' ],
            '_removeUpload'                => [ 'args' => [ 'ratesCsv', 'missing.csv' ], 'ability' => 'taxRate.viewAny' ],
            'createRate'                   => [ 'args' => [], 'ability' => 'taxRate.create' ],
            'editRate'                     => [ 'args' => [ 1 ], 'ability' => 'taxRate.update' ],
            // With no rate open, saving checks the create ability and stops.
            'saveRate'                     => [ 'args' => [], 'ability' => 'taxRate.create' ],
            'cancelRate'                   => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'confirmDeleteRate'            => [ 'args' => [ 1 ], 'ability' => 'taxRate.delete' ],
            'createClass'                  => [ 'args' => [], 'ability' => 'taxRate.create' ],
            'editClass'                    => [ 'args' => [ 1 ], 'ability' => 'taxRate.update' ],
            'saveClass'                    => [ 'args' => [], 'ability' => 'taxRate.update' ],
            'cancelClass'                  => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'confirmDeleteClass'           => [ 'args' => [ 1 ], 'ability' => 'taxRate.delete' ],
            'deleteClass'                  => [ 'args' => [ 'token' ], 'ability' => 'taxRate.delete' ],
            'cancelDeleteClass'            => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'openImport'                   => [ 'args' => [], 'ability' => 'taxRate.create' ],
            'checkImport'                  => [ 'args' => [], 'ability' => 'taxRate.create' ],
            'applyImport'                  => [ 'args' => [ 'token' ], 'ability' => 'taxRate.create' ],
            'closeImport'                  => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'downloadSample'               => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
            'exportRates'                  => [ 'args' => [], 'ability' => 'taxRate.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-notifications-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'notificationTemplate.viewAny' ],
        'actions' => [
            'resetFilters' => [ 'args' => [], 'ability' => 'notificationTemplate.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-notifications-edit' => [
        'mount'   => [ 'params' => [ 'template' => '@notification-template' ], 'ability' => 'notificationTemplate.view' ],
        'actions' => [
            'save'           => [ 'args' => [], 'ability' => 'notificationTemplate.update' ],
            'confirmReset'   => [ 'args' => [], 'ability' => 'notificationTemplate.update' ],
            'cancelReset'    => [ 'args' => [], 'ability' => 'notificationTemplate.view' ],
            'resetToDefault' => [ 'args' => [], 'ability' => 'notificationTemplate.update' ],
            'addLocale'      => [ 'args' => [], 'ability' => 'notificationTemplate.update' ],
            'sendTest'       => [ 'args' => [], 'ability' => 'notificationTemplate.update' ],
        ],
    ],
    'artisanpack-ecommerce-admin-webhooks-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'webhookSubscription.viewAny' ],
        'actions' => [
            'sort'                         => [ 'args' => [ 'name' ], 'ability' => 'webhookSubscription.viewAny' ],
            'resetFilters'                 => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'selectAllMatchingRows'        => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'clearSelection'               => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'runBulkAction'                => [ 'args' => [ 'export' ], 'ability' => 'webhookSubscription.viewAny' ],
            'confirmBulkAction'            => [ 'args' => [ 'token' ], 'ability' => 'webhookSubscription.viewAny' ],
            'cancelBulkAction'             => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'exportCsv'                    => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'getTableExportData'           => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'webhookSubscription.viewAny' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'webhookSubscription.viewAny' ],
            'create'                       => [ 'args' => [], 'ability' => 'webhookSubscription.create' ],
            'edit'                         => [ 'args' => [ 1 ], 'ability' => 'webhookSubscription.update' ],
            // With no subscription open, saving creates one.
            'save'                         => [ 'args' => [], 'ability' => 'webhookSubscription.create' ],
            'confirmDelete'                => [ 'args' => [ 1 ], 'ability' => 'webhookSubscription.delete' ],
            'cancelDelete'                 => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'delete'                       => [ 'args' => [ 'token' ], 'ability' => 'webhookSubscription.delete' ],
            'confirmRotate'                => [ 'args' => [ 1 ], 'ability' => 'webhookSubscription.update' ],
            'cancelRotate'                 => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'rotateSecret'                 => [ 'args' => [ 'token' ], 'ability' => 'webhookSubscription.update' ],
            'reEnable'                     => [ 'args' => [ 1 ], 'ability' => 'webhookSubscription.update' ],
            'dismissSecret'                => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-webhooks-show' => [
        'mount'   => [ 'params' => [ 'subscription' => '@webhook-subscription' ], 'ability' => 'webhookSubscription.viewAny' ],
        'actions' => [
            'sort'                         => [ 'args' => [ 'attempts' ], 'ability' => 'webhookSubscription.viewAny' ],
            'resetFilters'                 => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'selectAllMatchingRows'        => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'clearSelection'               => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'runBulkAction'                => [ 'args' => [ 'export' ], 'ability' => 'webhookSubscription.viewAny' ],
            'confirmBulkAction'            => [ 'args' => [ 'token' ], 'ability' => 'webhookSubscription.viewAny' ],
            'cancelBulkAction'             => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'exportCsv'                    => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'getTableExportData'           => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'queryStringHandlesPagination' => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'getPage'                      => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'previousPage'                 => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'nextPage'                     => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'gotoPage'                     => [ 'args' => [ 2 ], 'ability' => 'webhookSubscription.viewAny' ],
            'resetPage'                    => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'setPage'                      => [ 'args' => [ 1 ], 'ability' => 'webhookSubscription.viewAny' ],
            'openDelivery'                 => [ 'args' => [ 1 ], 'ability' => 'webhookSubscription.viewAny' ],
            'closeDelivery'                => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'replay'                       => [ 'args' => [ 'token' ], 'ability' => 'webhookSubscription.update' ],
            'confirmRotate'                => [ 'args' => [ 1 ], 'ability' => 'webhookSubscription.update' ],
            'cancelRotate'                 => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
            'rotateSecret'                 => [ 'args' => [ 'token' ], 'ability' => 'webhookSubscription.update' ],
            'reEnable'                     => [ 'args' => [ 1 ], 'ability' => 'webhookSubscription.update' ],
            'dismissSecret'                => [ 'args' => [], 'ability' => 'webhookSubscription.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-order-statuses-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'orderSubstatus.viewAny' ],
        'actions' => [
            'create'        => [ 'args' => [ 'processing' ], 'ability' => 'orderSubstatus.create' ],
            'edit'          => [ 'args' => [ 1 ], 'ability' => 'orderSubstatus.update' ],
            // With no sub-status open, saving creates one.
            'save'          => [ 'args' => [], 'ability' => 'orderSubstatus.create' ],
            'move'          => [ 'args' => [ 1, 1 ], 'ability' => 'orderSubstatus.update' ],
            'confirmDelete' => [ 'args' => [ 1 ], 'ability' => 'orderSubstatus.delete' ],
            'cancelDelete'  => [ 'args' => [], 'ability' => 'orderSubstatus.viewAny' ],
            'delete'        => [ 'args' => [ 'token' ], 'ability' => 'orderSubstatus.delete' ],
        ],
    ],

    'artisanpack-ecommerce-admin-kanban-boards-index' => [
        'mount'   => [ 'params' => [], 'ability' => 'kanbanBoard.viewAny' ],
        'actions' => [
            'create'        => [ 'args' => [], 'ability' => 'kanbanBoard.create' ],
            // With the drawer closed, saving does nothing past the check.
            'save'          => [ 'args' => [], 'ability' => 'kanbanBoard.create' ],
            'makeDefault'   => [ 'args' => [ 1 ], 'ability' => 'kanbanBoard.update' ],
            'move'          => [ 'args' => [ 1, 1 ], 'ability' => 'kanbanBoard.update' ],
            'confirmDelete' => [ 'args' => [ 1 ], 'ability' => 'kanbanBoard.delete' ],
            'cancelDelete'  => [ 'args' => [], 'ability' => 'kanbanBoard.viewAny' ],
            'delete'        => [ 'args' => [ 'token' ], 'ability' => 'kanbanBoard.delete' ],
        ],
    ],
    'artisanpack-ecommerce-admin-kanban-boards-edit' => [
        'mount'   => [ 'params' => [ 'board' => '@kanban-board' ], 'ability' => 'kanbanBoard.view' ],
        'actions' => [
            'save'                => [ 'args' => [], 'ability' => 'kanbanBoard.update' ],
            'replaceComplexRules' => [ 'args' => [], 'ability' => 'kanbanBoard.update' ],
            'addRule'             => [ 'args' => [ 'conditions' ], 'ability' => 'kanbanBoard.update' ],
            'removeRule'          => [ 'args' => [ 'conditions', 0 ], 'ability' => 'kanbanBoard.update' ],
            'moveRule'            => [ 'args' => [ 'conditions', 0, 1 ], 'ability' => 'kanbanBoard.update' ],
            'reorderRules'        => [ 'args' => [ 'conditions', [] ], 'ability' => 'kanbanBoard.update' ],
            'toggleRule'          => [ 'args' => [ 'conditions', 'missing' ], 'ability' => 'kanbanBoard.update' ],
            // The screen re-authorizes kanbanBoard.view on every request; config rows then need admin access, which kanbanBoard.viewAny gives.
            'addConfigRow'     => [ 'args' => [ 'promotion-condition', 'min-subtotal', 'ruleRows.conditions.0.config', 'tiers' ], 'ability' => 'kanbanBoard.viewAny' ],
            'removeConfigRow'  => [ 'args' => [ 'promotion-condition', 'min-subtotal', 'ruleRows.conditions.0.config', 'tiers', 0 ], 'ability' => 'kanbanBoard.viewAny' ],
            // The picker source then needs product.viewAny.
            'searchPicker'     => [ 'args' => [ 'a', 'product', 'ruleRows.conditions.0.config.product_ids' ], 'ability' => 'product.viewAny' ],
            'optionsForPicker' => [ 'args' => [ 'product', 'ruleRows.conditions.0.config.product_ids' ], 'ability' => 'product.viewAny' ],
        ],
    ],
    'artisanpack-ecommerce-admin-kanban-board-columns' => [
        'mount'   => [ 'params' => [ 'board' => '@kanban-board-model' ], 'ability' => 'kanbanBoard.view' ],
        'actions' => [
            'create'        => [ 'args' => [], 'ability' => 'kanbanBoard.update' ],
            'edit'          => [ 'args' => [ 1 ], 'ability' => 'kanbanBoard.update' ],
            'addWidget'     => [ 'args' => [], 'ability' => 'kanbanBoard.update' ],
            'removeWidget'  => [ 'args' => [ 0 ], 'ability' => 'kanbanBoard.update' ],
            'moveWidget'    => [ 'args' => [ 0, 1 ], 'ability' => 'kanbanBoard.update' ],
            'save'          => [ 'args' => [], 'ability' => 'kanbanBoard.update' ],
            'move'          => [ 'args' => [ 1, 1 ], 'ability' => 'kanbanBoard.update' ],
            'confirmDelete' => [ 'args' => [ 1 ], 'ability' => 'kanbanBoard.update' ],
            'cancelDelete'  => [ 'args' => [], 'ability' => 'kanbanBoard.view' ],
            'delete'        => [ 'args' => [ 'token' ], 'ability' => 'kanbanBoard.update' ],
        ],
    ],
    'artisanpack-ecommerce-admin-kanban-board-automations' => [
        'mount'   => [ 'params' => [ 'board' => '@kanban-board-model' ], 'ability' => 'kanbanBoard.view' ],
        'actions' => [
            'refreshColumns'           => [ 'args' => [], 'ability' => 'kanbanBoard.view' ],
            'create'                   => [ 'args' => [], 'ability' => 'kanbanBoard.update' ],
            'edit'                     => [ 'args' => [ 1 ], 'ability' => 'kanbanBoard.update' ],
            'replaceComplexConditions' => [ 'args' => [], 'ability' => 'kanbanBoard.update' ],
            'save'                     => [ 'args' => [], 'ability' => 'kanbanBoard.update' ],
            'toggleActive'             => [ 'args' => [ 1 ], 'ability' => 'kanbanBoard.update' ],
            'confirmDelete'            => [ 'args' => [ 1 ], 'ability' => 'kanbanBoard.update' ],
            'cancelDelete'             => [ 'args' => [], 'ability' => 'kanbanBoard.view' ],
            'delete'                   => [ 'args' => [ 'token' ], 'ability' => 'kanbanBoard.update' ],
            'addRule'                  => [ 'args' => [ 'conditions' ], 'ability' => 'kanbanBoard.update' ],
            'removeRule'               => [ 'args' => [ 'conditions', 0 ], 'ability' => 'kanbanBoard.update' ],
            'moveRule'                 => [ 'args' => [ 'conditions', 0, 1 ], 'ability' => 'kanbanBoard.update' ],
            'reorderRules'             => [ 'args' => [ 'conditions', [] ], 'ability' => 'kanbanBoard.update' ],
            'toggleRule'               => [ 'args' => [ 'conditions', 'missing' ], 'ability' => 'kanbanBoard.update' ],
            // The screen re-authorizes kanbanBoard.view on every request; config rows then need admin access, which kanbanBoard.viewAny gives.
            'addConfigRow'     => [ 'args' => [ 'kanban-trigger', 'send-email', 'form.trigger_config', 'tiers' ], 'ability' => 'kanbanBoard.viewAny' ],
            'removeConfigRow'  => [ 'args' => [ 'kanban-trigger', 'send-email', 'form.trigger_config', 'tiers', 0 ], 'ability' => 'kanbanBoard.viewAny' ],
            // The picker source then needs product.viewAny.
            'searchPicker'     => [ 'args' => [ 'a', 'product', 'ruleRows.conditions.0.config.product_ids' ], 'ability' => 'product.viewAny' ],
            'optionsForPicker' => [ 'args' => [ 'product', 'ruleRows.conditions.0.config.product_ids' ], 'ability' => 'product.viewAny' ],
        ],
    ],

    'artisanpack-ecommerce-admin-reports-show' => [
        'mount'   => [ 'params' => [ 'report' => 'sales' ], 'ability' => 'report.view' ],
        'actions' => [
            'export' => [ 'args' => [], 'ability' => 'report.view' ],
        ],
    ],
    'artisanpack-ecommerce-admin-settings' => [
        'mount'   => [ 'params' => [ 'group' => 'tax' ], 'ability' => 'settings.view' ],
        'actions' => [
            'save'                      => [ 'args' => [], 'ability' => 'settings.update' ],
            'confirmBaseCurrencyChange' => [ 'args' => [], 'ability' => 'settings.update' ],
            'resetToDefault'            => [ 'args' => [ 'tax.prices_include_tax' ], 'ability' => 'settings.update' ],
            'addMapRow'                 => [ 'args' => [ 'localization.tax_labels' ], 'ability' => 'settings.update' ],
            'removeMapRow'              => [ 'args' => [ 'localization.tax_labels', 0 ], 'ability' => 'settings.update' ],
        ],
    ],

    // The WithPickers concern, which screens mix in.
    'matrix-pickers' => [
        'mount'   => [ 'params' => [], 'ability' => null ],
        'actions' => [
            'searchPicker'     => [ 'args' => [ 'a', 'customer', 'customerId' ], 'ability' => 'customer.viewAny' ],
            'optionsForPicker' => [ 'args' => [ 'customer', 'customerId' ], 'ability' => 'customer.viewAny', 'denied' => 'empty' ],
        ],
    ],

    // The WithConfigForms concern, which config forms mix in.
    'matrix-config-forms' => [
        'mount'   => [ 'params' => [], 'ability' => null ],
        'actions' => [
            'addConfigRow'    => [ 'args' => [ 'shipping-method', 'price-based', 'config', 'tiers' ], 'ability' => null ],
            'removeConfigRow' => [ 'args' => [ 'shipping-method', 'price-based', 'config', 'tiers', 0 ], 'ability' => null ],
        ],
    ],
];

/**
 * Test fixtures that bring shared concerns into the matrix.
 */
$GLOBALS['matrixFixtureComponents'] = [
    'matrix-pickers'      => Tests\Fixtures\Livewire\MatrixPickers::class,
    'matrix-config-forms' => Tests\Fixtures\Livewire\MatrixConfigForms::class,
];

/**
 * Resolves mount parameters: `@order` becomes the id of an order created
 * for the test, and `@order-model` that order itself; `@customer` and
 * `@customer-model` do the same for a customer, and `@promotion-model`
 * gives a coupon promotion.
 *
 * @param  array<string, mixed>  $params
 *
 * @return array<string, mixed>
 */
function matrixParams( array $params ): array
{
    return array_map( static function ( mixed $value ): mixed {
        if ( '@order' === $value ) {
            return $GLOBALS['matrixOrderId'] ??= ArtisanPackUI\Ecommerce\Models\Order::factory()->create()->id;
        }

        if ( '@customer' === $value ) {
            return $GLOBALS['matrixCustomerId'] ??= ArtisanPackUI\Ecommerce\Models\Customer::factory()->create()->id;
        }

        if ( '@customer-model' === $value ) {
            return ArtisanPackUI\Ecommerce\Models\Customer::query()->findOrFail( $GLOBALS['matrixCustomerId'] ??= ArtisanPackUI\Ecommerce\Models\Customer::factory()->create()->id );
        }

        if ( '@promotion-model' === $value ) {
            return ArtisanPackUI\Ecommerce\Models\Promotion::query()->findOrFail( $GLOBALS['matrixPromotionId'] ??= ArtisanPackUI\Ecommerce\Models\Promotion::factory()->coupon()->create()->id );
        }

        if ( '@notification-template' === $value ) {
            return $GLOBALS['matrixNotificationTemplateId'] ??= ArtisanPackUI\Ecommerce\Models\NotificationTemplate::factory()->create()->id;
        }

        if ( '@webhook-subscription' === $value ) {
            return $GLOBALS['matrixWebhookSubscriptionId'] ??= ArtisanPackUI\Ecommerce\Models\WebhookSubscription::factory()->create()->id;
        }

        if ( '@kanban-board' === $value ) {
            return $GLOBALS['matrixKanbanBoardId'] ??= ArtisanPackUI\Ecommerce\Models\KanbanBoard::factory()->create()->id;
        }

        if ( '@kanban-board-model' === $value ) {
            return ArtisanPackUI\Ecommerce\Models\KanbanBoard::query()->findOrFail( $GLOBALS['matrixKanbanBoardId'] ??= ArtisanPackUI\Ecommerce\Models\KanbanBoard::factory()->create()->id );
        }

        if ( '@order-model' === $value ) {
            return ArtisanPackUI\Ecommerce\Models\Order::query()->findOrFail( $GLOBALS['matrixOrderId'] ??= ArtisanPackUI\Ecommerce\Models\Order::factory()->create()->id );
        }

        return $value;
    }, $params );
}

/**
 * Livewire lifecycle hooks, which the client cannot call.
 */
const MATRIX_LIFECYCLE = '/^(render|rendering|rendered|exception|placeholder)$|^(mount|boot|booted|hydrate|dehydrate|updating|updated)([A-Z_].*)?$/';

/**
 * The client-callable actions of a component: its public instance methods
 * that Livewire's base component does not define.
 *
 * @return array<int, string>
 */
function matrixActions( string $class ): array
{
    $base = array_map(
        static fn ( ReflectionMethod $method ): string => $method->getName(),
        ( new ReflectionClass( Component::class ) )->getMethods( ReflectionMethod::IS_PUBLIC ),
    );

    $actions = array_filter(
        ( new ReflectionClass( $class ) )->getMethods( ReflectionMethod::IS_PUBLIC ),
        static fn ( ReflectionMethod $method ): bool => ! $method->isStatic()
            && ! in_array( $method->getName(), $base, true )
            && 1 !== preg_match( MATRIX_LIFECYCLE, $method->getName() )
            && [] === $method->getAttributes( Computed::class ),
    );

    $names = array_map( static fn ( ReflectionMethod $method ): string => $method->getName(), $actions );
    sort( $names );

    return $names;
}

/**
 * A user who can enter the admin but does not hold `$ability`.
 */
function matrixIntruder( ?string $ability )
{
    if ( null !== $ability ) {
        $unrelated = 'kanbanBoard.viewAny' === $ability ? 'review.viewAny' : 'kanbanBoard.viewAny';

        Illuminate\Support\Facades\Gate::define( 'ecommerce.' . $unrelated, static fn (): bool => true );
    }

    return makeUser();
}

/**
 * Grants exactly `$abilities` (null entries mean "may enter the admin").
 *
 * @param  array<int, string|null>  $abilities
 */
function matrixGrant( array $abilities ): void
{
    $abilities = array_values( array_filter( $abilities ) );

    grantAbilities( [] === $abilities ? [ 'review.viewAny' ] : $abilities );
}

/**
 * The components under test: the registered ones plus any test fixtures.
 *
 * @return array<string, class-string>
 */
function matrixComponents(): array
{
    return EcommerceAdminLivewireServiceProvider::LIVEWIRE_COMPONENTS + ( $GLOBALS['matrixFixtureComponents'] ?? [] );
}

dataset( 'admin components', static fn (): array => array_map(
    static fn ( string $name ): array => [ $name ],
    array_combine( array_keys( MATRIX ), array_keys( MATRIX ) ),
) );

dataset( 'admin actions', static function (): array {
    $rows = [];

    foreach ( MATRIX as $name => $definition ) {
        foreach ( array_keys( $definition['actions'] ) as $action ) {
            $rows[ $name . '::' . $action ] = [ $name, $action ];
        }
    }

    return [] === $rows ? [ 'none' => [ null, null ] ] : $rows;
} );

beforeEach( function (): void {
    $GLOBALS['matrixOrderId']                = null;
    $GLOBALS['matrixCustomerId']             = null;
    $GLOBALS['matrixPromotionId']            = null;
    $GLOBALS['matrixNotificationTemplateId'] = null;
    $GLOBALS['matrixWebhookSubscriptionId']  = null;
    $GLOBALS['matrixKanbanBoardId']          = null;

    foreach ( matrixComponents() as $name => $class ) {
        Livewire::component( $name, $class );
    }
} );

it( 'declares every component and every public action', function (): void {
    expect( array_keys( MATRIX ) )->toEqualCanonicalizing( array_keys( matrixComponents() ) );

    foreach ( matrixComponents() as $name => $class ) {
        $declared = array_keys( MATRIX[ $name ]['actions'] );
        sort( $declared );

        expect( $declared )->toBe( matrixActions( $class ), $name . ' has undeclared or stale actions in MATRIX.' );
    }
} );

it( 'registers every Livewire component class under src/Livewire', function (): void {
    $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/../../../src/Livewire', FilesystemIterator::SKIP_DOTS ) );
    $root  = realpath( __DIR__ . '/../../../src/Livewire' );

    $classes = collect( iterator_to_array( $files ) )
        ->map( static fn ( SplFileInfo $file ): string => (string) $file->getRealPath() )
        ->filter( static fn ( string $path ): bool => str_ends_with( $path, '.php' ) && ! str_contains( $path, DIRECTORY_SEPARATOR . 'Concerns' . DIRECTORY_SEPARATOR ) )
        ->map( static fn ( string $path ): string => 'ArtisanPackUI\\EcommerceAdminLivewire\\Livewire\\' . str_replace( [ $root . DIRECTORY_SEPARATOR, '.php', DIRECTORY_SEPARATOR ], [ '', '', '\\' ], $path ) )
        ->reject( static fn ( string $class ): bool => ( new ReflectionClass( $class ) )->isAbstract() )
        ->sort()
        ->values()
        ->all();

    expect( collect( EcommerceAdminLivewireServiceProvider::LIVEWIRE_COMPONENTS )->sort()->values()->all() )->toBe( $classes );
} );

it( 'refuses every admin route to a user who may not enter the admin', function (): void {
    $routes = collect( Route::getRoutes()->getRoutes() )
        ->filter( static fn ( RoutingRoute $route ): bool => str_starts_with( (string) $route->getName(), 'artisanpack.ecommerce.admin.' ) );

    expect( $routes )->not->toBeEmpty();

    $user = makeUser();

    $routes->each( function ( RoutingRoute $route ) use ( $user ): void {
        $this->actingAs( $user )
            ->call( $route->methods()[0], route( $route->getName(), array_fill_keys( $route->parameterNames(), '1' ) ) )
            ->assertForbidden();
    } );
} );

it( 'refuses to mount without the screen ability', function ( string $name ): void {
    $mount = MATRIX[ $name ]['mount'];

    Livewire::actingAs( null === $mount['ability'] ? makeUser() : matrixIntruder( $mount['ability'] ) )
        ->test( $name, matrixParams( $mount['params'] ) )
        ->assertForbidden();
} )->with( 'admin components' );

it( 'mounts with only the screen ability', function ( string $name ): void {
    $mount = MATRIX[ $name ]['mount'];
    matrixGrant( [ $mount['ability'] ] );

    Livewire::actingAs( makeUser() )
        ->test( $name, matrixParams( $mount['params'] ) )
        ->assertOk();
} )->with( 'admin components' );

it( 'refuses the action without its ability', function ( ?string $name, ?string $action ): void {
    if ( null === $name ) {
        expect( true )->toBeTrue();

        return;
    }

    $mount      = MATRIX[ $name ]['mount'];
    $definition = MATRIX[ $name ]['actions'][ $action ];

    $admin = makeUser();
    grantAdmin( $admin );
    $intruder = matrixIntruder( $definition['ability'] );

    $component = Livewire::actingAs( $admin )->test( $name, matrixParams( $mount['params'] ) )->assertOk();

    $this->actingAs( $intruder );

    $component->call( $action, ...$definition['args'] );

    if ( 'empty' === ( $definition['denied'] ?? 'forbidden' ) ) {
        $component->assertOk()->assertReturned( [] );

        return;
    }

    $component->assertForbidden();
} )->with( 'admin actions' );

it( 'allows the action with only its ability', function ( ?string $name, ?string $action ): void {
    if ( null === $name ) {
        expect( true )->toBeTrue();

        return;
    }

    $mount      = MATRIX[ $name ]['mount'];
    $definition = MATRIX[ $name ]['actions'][ $action ];
    matrixGrant( [ $mount['ability'], $definition['ability'] ] );

    Livewire::actingAs( makeUser() )
        ->test( $name, matrixParams( $mount['params'] ) )
        ->call( $action, ...$definition['args'] )
        ->assertOk();
} )->with( 'admin actions' );
