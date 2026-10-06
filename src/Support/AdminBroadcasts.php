<?php

/**
 * Real-time admin broadcasts.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The engine's admin broadcasts on `private-ecommerce.admin` (spec §5.5).
 *
 * The engine re-broadcasts order, payment, stock, and review events on that
 * channel when `artisanpack.ecommerce.graphql.subscriptions` is on. Screens
 * subscribe through Livewire's `echo-private:` listeners only when:
 *
 * - `artisanpack.ecommerce-admin-livewire.realtime.enabled` is on (it is
 *   off by default);
 * - the engine broadcasts, so its channel is registered; and
 * - the user passes the channel's own check (`order`, `product`, and
 *   `webhookSubscription` `viewAny`), so the subscription is not refused.
 *
 * Otherwise no listener is registered and the page behaves exactly as it
 * does without Echo.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class AdminBroadcasts
{
    /**
     * The private channel name, without the `private-` prefix.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const CHANNEL = 'ecommerce.admin';

    /**
     * An order changed system status.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ORDER_STATUS_CHANGED = 'orderStatusChanged';

    /**
     * An order's payment succeeded — a new paid order.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PAYMENT_SUCCEEDED = 'paymentSucceeded';

    /**
     * Stock was adjusted.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const STOCK_CHANGED = 'stockChanged';

    /**
     * A review was submitted.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const REVIEW_SUBMITTED = 'reviewSubmitted';

    /**
     * Overrides whether rebing/graphql-laravel counts as installed, in tests.
     *
     * @since 1.0.0
     *
     * @var bool|null
     */
    private static ?bool $fakeGraphQl = null;

    /**
     * Whether the user's screens subscribe to the admin channel.
     *
     * The engine registers the `ecommerce.admin` channel only when
     * rebing/graphql-laravel is installed, so without it nothing subscribes
     * (the channel would answer every subscription with a 403).
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user  The current user.
     *
     * @return bool
     */
    public static function enabled( ?Authenticatable $user ): bool
    {
        return null !== $user
            && (bool) config( 'artisanpack.ecommerce-admin-livewire.realtime.enabled', false )
            && (bool) config( 'artisanpack.ecommerce.graphql.subscriptions', false )
            && self::graphQlInstalled()
            && Authorization::allows( $user, 'order.viewAny' )
            && Authorization::allows( $user, 'product.viewAny' )
            && Authorization::allows( $user, 'webhookSubscription.viewAny' );
    }

    /**
     * Whether rebing/graphql-laravel is installed.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function graphQlInstalled(): bool
    {
        return self::$fakeGraphQl ?? class_exists( 'Rebing\\GraphQL\\GraphQL' );
    }

    /**
     * Pretends rebing/graphql-laravel is (or isn't) installed. Pass null to
     * go back to checking for the class.
     *
     * @since 1.0.0
     *
     * @param  bool|null  $installed  Whether it counts as installed.
     *
     * @return void
     */
    public static function fakeGraphQl( ?bool $installed ): void
    {
        self::$fakeGraphQl = $installed;
    }

    /**
     * The Livewire listener name for a broadcast.
     *
     * The engine names its broadcasts with `broadcastAs()`, so Echo needs
     * the leading dot.
     *
     * @since 1.0.0
     *
     * @param  string  $event  The broadcast name, e.g. `orderStatusChanged`.
     *
     * @return string
     */
    public static function listener( string $event ): string
    {
        return 'echo-private:' . self::CHANNEL . ',.' . $event;
    }

    /**
     * The order id a broadcast is about, or null.
     *
     * Only used to decide whether to look; the screen re-reads the order
     * from the database rather than trusting the payload.
     *
     * @since 1.0.0
     *
     * @param  mixed  $payload  The broadcast payload (`{ data: { <event>: { order: { id } } } }`).
     *
     * @return int|null
     */
    public static function orderId( mixed $payload ): ?int
    {
        $data  = is_array( $payload ) && is_array( $payload['data'] ?? null ) ? $payload['data'] : [];
        $event = [] === $data ? null : reset( $data );
        $id    = is_array( $event ) ? ( $event['order']['id'] ?? null ) : null;

        return is_numeric( $id ) ? (int) $id : null;
    }
}
