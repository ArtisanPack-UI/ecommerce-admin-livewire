<?php

/**
 * Notification templates list.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notifications;

use ArtisanPackUI\Ecommerce\Models\NotificationTemplate;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Support\NotificationTemplates;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Locale;

/**
 * The notification templates list (spec §7.8): one group per template key,
 * each row a channel and locale with its active state and when it was last
 * edited, linking to the editor.
 *
 * Mounting syncs the engine's catalog, so every registered template has a
 * default-locale row to edit.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Index extends Component
{
    use AuthorizesEcommerce;

    /**
     * Filters the list by channel; empty for every channel.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $channel = '';

    /**
     * Filters the list by locale; empty for every locale.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $locale = '';

    /**
     * Authorizes the screen and seeds missing catalog rows.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->authorizeEcommerce( 'viewAny', NotificationTemplate::class );

        NotificationTemplates::syncOnce();
    }

    /**
     * Re-authorizes the screen on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeEcommerce( 'viewAny', NotificationTemplate::class );
    }

    /**
     * Clears the filters.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function resetFilters(): void
    {
        $this->channel = '';
        $this->locale  = '';
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $rows = NotificationTemplate::query()
            ->orderBy( 'key' )
            ->orderBy( 'channel' )
            ->orderBy( 'locale' )
            ->get( [ 'id', 'key', 'channel', 'locale', 'is_active', 'updated_at' ] );

        $channels = $rows->pluck( 'channel' )->unique()->sort()->values();
        $locales  = $rows->pluck( 'locale' )->unique()->sort()->values();

        $filtered = $rows
            ->when( '' !== $this->channel, fn ( $collection ) => $collection->where( 'channel', $this->channel ) )
            ->when( '' !== $this->locale, fn ( $collection ) => $collection->where( 'locale', $this->locale ) );

        $groups = $filtered
            ->groupBy( 'key' )
            ->map( static function ( $templates, string $key ): array {
                $definition = $templates->first()->definition();

                return [
                    'key'       => $key,
                    'label'     => null === $definition ? Str::headline( $key ) : (string) $definition->label(),
                    'templates' => $templates->values(),
                ];
            } )
            ->sortBy( 'label', SORT_NATURAL | SORT_FLAG_CASE )
            ->values();

        return view( 'ecommerce-admin::livewire.notifications.index', [
            'groups'         => $groups,
            'total'          => $rows->count(),
            'channelOptions' => $channels->map( static fn ( string $channel ): array => [ 'id' => $channel, 'name' => self::channelLabel( $channel ) ] )->all(),
            'localeOptions'  => $locales->map( static fn ( string $locale ): array => [ 'id' => $locale, 'name' => self::localeLabel( $locale ) ] )->all(),
            'filtersActive'  => '' !== $this->channel || '' !== $this->locale,
        ] );
    }

    /**
     * A readable name for a channel.
     *
     * @since 1.0.0
     *
     * @param  string  $channel  Channel key.
     *
     * @return string
     */
    public static function channelLabel( string $channel ): string
    {
        return match ( $channel ) {
            'mail'     => __( 'Email' ),
            'database' => __( 'In-app' ),
            'sms'      => __( 'SMS' ),
            default    => Str::headline( $channel ),
        };
    }

    /**
     * A readable name for a locale, e.g. "German (de)".
     *
     * @since 1.0.0
     *
     * @param  string  $locale  Locale code.
     *
     * @return string
     */
    public static function localeLabel( string $locale ): string
    {
        $name = class_exists( Locale::class ) ? (string) Locale::getDisplayName( $locale, app()->getLocale() ) : '';

        return '' === $name || $name === $locale ? $locale : $name . ' (' . $locale . ')';
    }
}
