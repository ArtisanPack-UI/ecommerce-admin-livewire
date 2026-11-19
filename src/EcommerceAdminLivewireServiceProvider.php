<?php

/**
 * Ecommerce admin (Livewire) service provider.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire;

use ArtisanPack\LivewireUiComponents\Support\IconRegistry;
use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Console\Commands\InstallCommand;
use ArtisanPackUI\EcommerceAdminLivewire\Console\Commands\PruneImportsCommand;
use ArtisanPackUI\EcommerceAdminLivewire\Console\Commands\SyncPermissionsCommand;
use ArtisanPackUI\EcommerceAdminLivewire\Http\Middleware\EnsureAdminAccess;
use ArtisanPackUI\EcommerceAdminLivewire\Http\Middleware\ThrottleAdminMutations;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Categories;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Dashboard;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\DigitalFiles;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Inventory;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\KanbanBoards;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\LicenseKeys;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Navigation;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notes;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notifications;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\OrderStatuses;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Reports;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Reviews;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Settings;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Shipping;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Tags;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Tax;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Timeline;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Webhooks;
use ArtisanPackUI\EcommerceAdminLivewire\Pickers\CategoryPickerSource;
use ArtisanPackUI\EcommerceAdminLivewire\Pickers\CustomerPickerSource;
use ArtisanPackUI\EcommerceAdminLivewire\Pickers\ProductPickerSource;
use ArtisanPackUI\EcommerceAdminLivewire\Pickers\TagPickerSource;
use ArtisanPackUI\EcommerceAdminLivewire\Pickers\VariantPickerSource;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\CustomerTabRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\OrderPanelRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\PickerSourceRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ProductTypePanelRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\SettingsTabRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Spotlight\AdminSpotlight;
use ArtisanPackUI\EcommerceAdminLivewire\Spotlight\NullSpotlight;
use ArtisanPackUI\EcommerceAdminLivewire\Support\CmsFramework;
use ArtisanPackUI\EcommerceAdminLivewire\Support\CmsMenu;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ConfigSchemas;
use ArtisanPackUI\EcommerceAdminLivewire\View\Components;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Bootstraps the Livewire admin satellite.
 *
 * Registers the package with the engine's `SatelliteRegistry` first. When the
 * satellite has been uninstalled (`register()` returns false) nothing else is
 * wired: no views, routes, components, navigation, or hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class EcommerceAdminLivewireServiceProvider extends ServiceProvider
{
    /**
     * The package version reported to the engine's `SatelliteRegistry`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const VERSION = '1.0.0';

    /**
     * The Composer package name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PACKAGE_NAME = 'artisanpack-ui/ecommerce-admin-livewire';

    /**
     * The view and translation namespace.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const VIEW_NAMESPACE = 'ecommerce-admin';

    /**
     * The composed Blade components, keyed by their name after the
     * `artisanpack-ec-` prefix.
     *
     * `money`, `empty-state`, `address` and `address-form` share their names
     * with ecommerce-storefront-livewire's components, so in an app with
     * both, whichever registers last owns them. The admin's own views use
     * the `admin-` names, which only the admin registers.
     *
     * @since 1.0.0
     * @since 2.0.0 Added `admin-money`, `admin-empty-state`, `admin-address` and `admin-address-form`.
     *
     * @var array<string, class-string>
     */
    public const BLADE_COMPONENTS = [
        'address'              => Components\Address::class,
        'address-form'         => Components\AddressForm::class,
        'admin-address'        => Components\Address::class,
        'admin-address-form'   => Components\AddressForm::class,
        'admin-empty-state'    => Components\EmptyState::class,
        'admin-money'          => Components\Money::class,
        'bulk-action-bar'      => Components\BulkActionBar::class,
        'category-picker'      => Components\CategoryPicker::class,
        'config-form'          => Components\ConfigForm::class,
        'customer-picker'      => Components\CustomerPicker::class,
        'empty-state'          => Components\EmptyState::class,
        'money'                => Components\Money::class,
        'money-input'          => Components\MoneyInput::class,
        'percent-input'        => Components\PercentInput::class,
        'product-picker'       => Components\ProductPicker::class,
        'product-type-warning' => Components\ProductTypeWarning::class,
        'resource-table'       => Components\ResourceTable::class,
        'rule-builder'         => Components\RuleBuilder::class,
        'status-badge'         => Components\StatusBadge::class,
        'tag-picker'           => Components\TagPicker::class,
        'variant-picker'       => Components\VariantPicker::class,
    ];

    /**
     * The package's Livewire components, keyed by component name.
     *
     * @since 1.0.0
     *
     * @var array<string, class-string>
     */
    public const LIVEWIRE_COMPONENTS = [
        'artisanpack-ecommerce-admin-dashboard'                => Dashboard::class,
        'artisanpack-ecommerce-admin-navigation'               => Navigation::class,
        'artisanpack-ecommerce-admin-orders-index'             => Orders\Index::class,
        'artisanpack-ecommerce-admin-orders-show'              => Orders\Show::class,
        'artisanpack-ecommerce-admin-order-status'             => Orders\StatusPanel::class,
        'artisanpack-ecommerce-admin-order-refunds'            => Orders\RefundsPanel::class,
        'artisanpack-ecommerce-admin-order-fulfillment'        => Orders\FulfillmentPanel::class,
        'artisanpack-ecommerce-admin-order-edits'              => Orders\EditPanel::class,
        'artisanpack-ecommerce-admin-timeline'                 => Timeline::class,
        'artisanpack-ecommerce-admin-notes'                    => Notes::class,
        'artisanpack-ecommerce-admin-products-index'           => Products\Index::class,
        'artisanpack-ecommerce-admin-products-form'            => Products\Form::class,
        'artisanpack-ecommerce-admin-product-variable-panel'   => Products\Panels\VariablePanel::class,
        'artisanpack-ecommerce-admin-product-digital-panel'    => Products\Panels\DigitalPanel::class,
        'artisanpack-ecommerce-admin-product-children-panel'   => Products\Panels\ChildrenPanel::class,
        'artisanpack-ecommerce-admin-products-import'          => Products\Import::class,
        'artisanpack-ecommerce-admin-categories-index'         => Categories\Index::class,
        'artisanpack-ecommerce-admin-tags-index'               => Tags\Index::class,
        'artisanpack-ecommerce-admin-inventory-index'          => Inventory\Index::class,
        'artisanpack-ecommerce-admin-reviews-index'            => Reviews\Index::class,
        'artisanpack-ecommerce-admin-digital-files-index'      => DigitalFiles\Index::class,
        'artisanpack-ecommerce-admin-license-keys-index'       => LicenseKeys\Index::class,
        'artisanpack-ecommerce-admin-customers-index'          => Customers\Index::class,
        'artisanpack-ecommerce-admin-customers-show'           => Customers\Show::class,
        'artisanpack-ecommerce-admin-customer-orders'          => Customers\OrdersTab::class,
        'artisanpack-ecommerce-admin-customer-addresses'       => Customers\AddressesTab::class,
        'artisanpack-ecommerce-admin-customer-preferences'     => Customers\PreferencesTab::class,
        'artisanpack-ecommerce-admin-promotions-index'         => Promotions\Index::class,
        'artisanpack-ecommerce-admin-promotions-form'          => Promotions\Form::class,
        'artisanpack-ecommerce-admin-promotion-coupons'        => Promotions\CouponsPanel::class,
        'artisanpack-ecommerce-admin-promotion-usage'          => Promotions\UsagePanel::class,
        'artisanpack-ecommerce-admin-shipping-index'           => Shipping\Index::class,
        'artisanpack-ecommerce-admin-tax-index'                => Tax\Index::class,
        'artisanpack-ecommerce-admin-notifications-index'      => Notifications\Index::class,
        'artisanpack-ecommerce-admin-notifications-edit'       => Notifications\Edit::class,
        'artisanpack-ecommerce-admin-webhooks-index'           => Webhooks\Index::class,
        'artisanpack-ecommerce-admin-webhooks-show'            => Webhooks\Show::class,
        'artisanpack-ecommerce-admin-order-statuses-index'     => OrderStatuses\Index::class,
        'artisanpack-ecommerce-admin-kanban-boards-index'      => KanbanBoards\Index::class,
        'artisanpack-ecommerce-admin-kanban-boards-edit'       => KanbanBoards\Edit::class,
        'artisanpack-ecommerce-admin-kanban-board-columns'     => KanbanBoards\Columns::class,
        'artisanpack-ecommerce-admin-kanban-board-automations' => KanbanBoards\Automations::class,
        'artisanpack-ecommerce-admin-reports-show'             => Reports\Show::class,
        'artisanpack-ecommerce-admin-settings'                 => Settings\Show::class,
    ];

    /**
     * The built-in order detail panels, as `key => [ component, column, position ]`.
     * Satellites register theirs after these (default position 100) and may
     * unregister or replace one by key.
     *
     * @since 1.0.0
     *
     * @var array<string, array{0: string, 1: string, 2: int}>
     */
    public const ORDER_PANELS = [
        'fulfillment' => [ 'artisanpack-ecommerce-admin-order-fulfillment', 'main', 10 ],
        'refunds'     => [ 'artisanpack-ecommerce-admin-order-refunds', 'main', 20 ],
        'edits'       => [ 'artisanpack-ecommerce-admin-order-edits', 'main', 30 ],
    ];

    /**
     * Core product-type panels (spec §7.2): type key => Livewire component,
     * or null when the type needs no panel.
     *
     * @since 1.0.0
     *
     * @var array<string, string|null>
     */
    public const PRODUCT_TYPE_PANELS = [
        'simple'   => null,
        'variable' => Products\Panels\VariablePanel::class,
        'digital'  => Products\Panels\DigitalPanel::class,
        'grouped'  => Products\Panels\ChildrenPanel::class,
        'bundled'  => Products\Panels\ChildrenPanel::class,
    ];

    /**
     * Lucide icons the admin uses that livewire-ui-components doesn't ship.
     * A host's own registration of the same name wins.
     *
     * @since 2.0.0
     *
     * @var array<string, string>
     */
    public const ICONS = [
        'archive'            => '<rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
        'banknote'           => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
        'bell'               => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'box'                => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
        'chart-column'       => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
        'circle-x'           => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
        'clock'              => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'columns-3'          => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/><path d="M15 3v18"/>',
        'credit-card'        => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
        'file-down'          => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/>',
        'filter'             => '<path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"/>',
        'folder'             => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'key'                => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
        'map-pin'            => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
        'megaphone'          => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
        'percent'            => '<line x1="19" x2="5" y1="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
        'phone'              => '<path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"/>',
        'rotate-cw'          => '<path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/>',
        'shield-alert'       => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
        'shopping-bag'       => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'sliders-horizontal' => '<line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/>',
        'tag'                => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5"/>',
        'ticket'             => '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>',
        'truck'              => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
        'user-minus'         => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="22" x2="16" y1="11" y2="11"/>',
        'user-plus'          => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
        'users'              => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'zap'                => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
        'zap-off'            => '<path d="M10.513 4.856 13.12 2.17a.5.5 0 0 1 .86.46l-1.377 4.317"/><path d="M15.656 10H20a1 1 0 0 1 .78 1.63l-1.72 1.773"/><path d="M16.273 16.273 10.88 21.83a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14H4a1 1 0 0 1-.78-1.63l4.507-4.643"/><path d="m2 2 20 20"/>',
    ];

    /**
     * Registers the package configuration and the extension registries.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/artisanpack/ecommerce-admin-livewire.php',
            'artisanpack.ecommerce-admin-livewire',
        );

        $this->app->singleton( PickerSourceRegistry::class, static function (): PickerSourceRegistry {
            $registry = new PickerSourceRegistry();

            $registry->register( 'product', new ProductPickerSource() );
            $registry->register( 'variant', new VariantPickerSource() );
            $registry->register( 'customer', new CustomerPickerSource() );
            $registry->register( 'category', new CategoryPickerSource() );
            $registry->register( 'tag', new TagPickerSource() );

            return $registry;
        } );

        $this->app->singleton( ConfigFormRegistry::class, static function (): ConfigFormRegistry {
            $registry = new ConfigFormRegistry();

            ConfigSchemas::register( $registry );

            return $registry;
        } );

        $this->app->singleton( ProductTypePanelRegistry::class, static function (): ProductTypePanelRegistry {
            $registry = new ProductTypePanelRegistry();

            foreach ( self::PRODUCT_TYPE_PANELS as $type => $component ) {
                $registry->register( $type, $component );
            }

            return $registry;
        } );

        $this->app->singleton( CustomerTabRegistry::class, static function (): CustomerTabRegistry {
            $registry = new CustomerTabRegistry();

            self::registerCustomerTabs( $registry );

            return $registry;
        } );

        $this->app->singleton( SettingsTabRegistry::class, static fn (): SettingsTabRegistry => new SettingsTabRegistry() );

        $this->app->singleton( OrderPanelRegistry::class, static function (): OrderPanelRegistry {
            $registry = new OrderPanelRegistry();

            foreach ( self::ORDER_PANELS as $key => [ $component, $column, $position ] ) {
                $registry->register( $key, $component, $column, $position );
            }

            return $registry;
        } );
    }

    /**
     * Registers the built-in customer detail tabs (spec §7.4) at positions
     * 10–50, leaving room for satellites before, between, and after them.
     *
     * @since 1.0.0
     *
     * @param  CustomerTabRegistry  $registry  The registry.
     *
     * @return void
     */
    public static function registerCustomerTabs( CustomerTabRegistry $registry ): void
    {
        $registry->register( 'orders', static fn (): string => __( 'Orders' ), 'artisanpack-ecommerce-admin-customer-orders', 10, 'customer', 'order.viewAny' );
        $registry->register( 'addresses', static fn (): string => __( 'Addresses' ), 'artisanpack-ecommerce-admin-customer-addresses', 20 );
        $registry->register( 'notifications', static fn (): string => __( 'Notification preferences' ), 'artisanpack-ecommerce-admin-customer-preferences', 30 );
        $registry->register( 'notes', static fn (): string => __( 'Notes' ), 'artisanpack-ecommerce-admin-notes', 40, 'subject' );
        $registry->register( 'activity', static fn (): string => __( 'Activity' ), 'artisanpack-ecommerce-admin-timeline', 50, 'subject' );
    }

    /**
     * Registers the satellite and, when it is active, wires the admin.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function boot(): void
    {
        if ( ! $this->registerSatellite() ) {
            return;
        }

        $this->registerPublishing();
        $this->registerViews();
        $this->registerTranslations();
        $this->registerCommands();
        $this->registerBladeComponents();
        $this->registerIcons();
        $this->registerLayoutResolver();
        $this->registerLivewireComponents();
        $this->registerRoutes();
        $this->registerSpotlight();
        CmsMenu::subscribe();
    }

    /**
     * The descriptor this package registers with the engine (spec §13).
     *
     * The admin owns no tables, columns, migrations, meta namespaces, or
     * product types, so it ships no uninstaller.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public static function satelliteDescriptor(): array
    {
        return [
            'package_name'    => self::PACKAGE_NAME,
            'version'         => self::VERSION,
            'label'           => __( 'Admin (Livewire)' ),
            'migration_paths' => [],
            'config_keys'     => [ 'artisanpack.ecommerce-admin-livewire' ],
            'meta_namespaces' => [],
            'tables'          => [],
            'columns'         => [],
            'product_types'   => [],
        ];
    }

    /**
     * Makes the configured `admin.middleware` persistent: class names as
     * given, aliases resolved to their classes. Middleware groups (`web`)
     * are left out; Livewire's update route has its own.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function registerPersistentMiddleware(): void
    {
        if ( ! class_exists( Livewire::class ) ) {
            return;
        }

        $middleware = self::hostPersistentMiddleware();

        if ( [] !== $middleware ) {
            Livewire::addPersistentMiddleware( $middleware );
        }
    }

    /**
     * The middleware classes behind `admin.middleware`, without groups.
     *
     * @since 1.0.0
     *
     * @return array<int, class-string>
     */
    public static function hostPersistentMiddleware(): array
    {
        $router  = app( 'router' );
        $aliases = $router->getMiddleware();
        $groups  = $router->getMiddlewareGroups();
        $classes = [];

        foreach ( (array) config( 'artisanpack.ecommerce-admin-livewire.admin.middleware', [] ) as $entry ) {
            if ( ! is_string( $entry ) || '' === $entry ) {
                continue;
            }

            $name = explode( ':', $entry, 2 )[0];

            if ( isset( $groups[ $name ] ) ) {
                continue;
            }

            $class = $aliases[ $name ] ?? $name;

            if ( is_string( $class ) && class_exists( $class ) ) {
                $classes[] = $class;
            }
        }

        return array_values( array_unique( $classes ) );
    }

    /**
     * Registers the package with the engine's `SatelliteRegistry`.
     *
     * @since 1.0.0
     *
     * @return bool True when the satellite is active.
     */
    protected function registerSatellite(): bool
    {
        return $this->app->make( SatelliteRegistry::class )->register( self::satelliteDescriptor() );
    }

    /**
     * Registers the config, views, and translations publish tags.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerPublishing(): void
    {
        $this->publishes(
            [
                __DIR__ . '/../config/artisanpack/ecommerce-admin-livewire.php' => config_path( 'artisanpack/ecommerce-admin-livewire.php' ),
            ],
            'ecommerce-admin-config',
        );

        $this->publishes(
            [
                __DIR__ . '/../resources/views' => resource_path( 'views/vendor/' . self::VIEW_NAMESPACE ),
            ],
            'ecommerce-admin-views',
        );

        $this->publishes(
            [
                __DIR__ . '/../lang' => $this->app->langPath( 'vendor/' . self::VIEW_NAMESPACE ),
            ],
            'ecommerce-admin-lang',
        );
    }

    /**
     * Loads the package views under the `ecommerce-admin` namespace.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom( __DIR__ . '/../resources/views', self::VIEW_NAMESPACE );
    }

    /**
     * Loads the JSON translation catalogues.
     *
     * Published catalogues in `lang/vendor/ecommerce-admin` are loaded too so a
     * host can override individual strings.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerTranslations(): void
    {
        $this->loadJsonTranslationsFrom( __DIR__ . '/../lang' );
        $this->loadJsonTranslationsFrom( $this->app->langPath( 'vendor/' . self::VIEW_NAMESPACE ) );
    }

    /**
     * Registers the Artisan commands.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        if ( ! $this->app->runningInConsole() ) {
            return;
        }

        $this->commands( [
            InstallCommand::class,
            PruneImportsCommand::class,
            SyncPermissionsCommand::class,
        ] );
    }

    /**
     * Shares the layout every page view extends.
     *
     * Pages extend `$ecommerceAdminLayout`: cms-framework's admin layout when
     * it is installed, else the package's standalone layout. Under the CMS
     * layout, `$ecommerceAdminPushesAssets` tells the page to push the
     * Livewire assets onto the layout's stacks.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerLayoutResolver(): void
    {
        View::composer( self::VIEW_NAMESPACE . '::pages.*', static function ( ViewContract $view ): void {
            $usesCms = CmsFramework::isInstalled();

            $view->with( [
                'ecommerceAdminLayout'       => $usesCms ? CmsFramework::LAYOUT : self::VIEW_NAMESPACE . '::layouts.app',
                'ecommerceAdminPushesAssets' => $usesCms,
            ] );
        } );
    }

    /**
     * Registers the Livewire components and the persistent middleware.
     *
     * The route middleware only guards the initial page load. Registering the
     * access check and the `ecommerce.admin.mutate` limiter as persistent
     * makes Livewire re-run them on every update request from an admin page.
     * So do the host's own `admin.middleware` entries (2FA, `verified`,
     * `password.confirm`, ...), once every alias is known.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerLivewireComponents(): void
    {
        if ( ! class_exists( Livewire::class ) ) {
            return;
        }

        foreach ( self::LIVEWIRE_COMPONENTS as $name => $class ) {
            Livewire::component( $name, $class );
        }

        Livewire::addPersistentMiddleware( [ EnsureAdminAccess::class, ThrottleAdminMutations::class ] );

        $this->app->booted( fn () => $this->registerPersistentMiddleware() );
    }

    /**
     * Registers the admin routes and the access middleware alias.
     *
     * Skipped when the routes are cached (the cache already holds them) or
     * when `admin.routes_enabled` is false.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        Route::aliasMiddleware( EnsureAdminAccess::ALIAS, EnsureAdminAccess::class );
        Route::aliasMiddleware( ThrottleAdminMutations::ALIAS, ThrottleAdminMutations::class );

        if ( $this->app->routesAreCached() ) {
            return;
        }

        if ( ! (bool) config( 'artisanpack.ecommerce-admin-livewire.admin.routes_enabled', true ) ) {
            return;
        }

        $this->loadRoutesFrom( __DIR__ . '/../routes/admin.php' );
    }

    /**
     * Adds the admin's results to livewire-ui-components' spotlight (spec
     * §8.1) unless the palette is disabled.
     *
     * The spotlight route resolves the host's search class before it
     * applies the results filter, so when the host has none an empty
     * stand-in is bound in its place.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerSpotlight(): void
    {
        if ( ! AdminSpotlight::enabled() ) {
            return;
        }

        addFilter( AdminSpotlight::COMMANDS_FILTER, [ AdminSpotlight::class, 'appendTo' ] );

        $class = config( 'artisanpack.livewire-ui-components.components.spotlight.class' );

        if ( is_string( $class ) && '' !== $class && ! class_exists( $class ) && ! $this->app->bound( $class ) ) {
            $this->app->bind( $class, NullSpotlight::class );
        }
    }

    /**
     * Registers the admin's extra icons with livewire-ui-components.
     *
     * @since 2.0.0
     *
     * @return void
     */
    protected function registerIcons(): void
    {
        $missing = array_filter(
            self::ICONS,
            static fn ( string $body, string $name ): bool => ! IconRegistry::has( $name ),
            ARRAY_FILTER_USE_BOTH,
        );

        if ( [] !== $missing ) {
            IconRegistry::register( $missing );
        }
    }

    /**
     * Registers the `<x-artisanpack-ec-…>` Blade components.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerBladeComponents(): void
    {
        foreach ( self::BLADE_COMPONENTS as $name => $class ) {
            Blade::component( 'artisanpack-ec-' . $name, $class );
        }
    }
}
