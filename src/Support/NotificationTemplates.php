<?php

/**
 * Notification template catalog sync.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Registries\NotificationTemplateRegistry;
use ArtisanPackUI\Ecommerce\Services\NotificationTemplateService;
use ArtisanPackUI\EcommerceAdminLivewire\EcommerceAdminLivewireServiceProvider;
use Illuminate\Support\Facades\Cache;

/**
 * Seeds the notification template rows the catalog needs.
 *
 * `NotificationTemplateService::sync()` writes to the database, so the
 * notifications screen runs it once per deploy (the admin version, the
 * catalog keys, and the default locale), not on every page view. The
 * install command runs it outright.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class NotificationTemplates
{
    /**
     * Cache key prefix of the "already synced" marker.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const CACHE_PREFIX = 'artisanpack.ecommerce-admin-livewire.notifications.synced.';

    /**
     * Syncs the catalog unless it was synced for this deploy already.
     *
     * @since 1.0.0
     *
     * @return bool Whether it synced.
     */
    public static function syncOnce(): bool
    {
        $key = self::CACHE_PREFIX . self::fingerprint();

        if ( Cache::has( $key ) ) {
            return false;
        }

        self::sync();

        Cache::forever( $key, true );

        return true;
    }

    /**
     * Syncs the catalog now.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function sync(): void
    {
        app( NotificationTemplateService::class )->sync();
    }

    /**
     * What a sync depends on: the admin version, the catalog keys (a
     * satellite adding a template changes them), and the default locale.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private static function fingerprint(): string
    {
        $keys = app( NotificationTemplateRegistry::class )->keys();
        sort( $keys );

        return hash( 'sha256', implode( '|', [
            EcommerceAdminLivewireServiceProvider::VERSION,
            app( NotificationTemplateService::class )->defaultLocale(),
            implode( ',', $keys ),
        ] ) );
    }
}
