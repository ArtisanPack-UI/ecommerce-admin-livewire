<?php

/**
 * Webhook display helpers.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\WebhookDelivery;
use ArtisanPackUI\Ecommerce\Models\WebhookSubscription;
use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
use ArtisanPackUI\Ecommerce\Webhooks\WebhookPayloadFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Event options, delivery statuses, and secrets for the webhook screens
 * (spec §7.8).
 *
 * A delivery's status is read from its ledger columns:
 *
 * - `delivered` — `delivered_at` is set;
 * - `pending`   — queued, never attempted (`attempts` is 0, `next_retry_at` set);
 * - `retrying`  — attempted, another try is scheduled (`next_retry_at` set);
 * - `failed`    — not delivered and nothing scheduled (the engine gave up).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class Webhooks
{
    /**
     * Delivery statuses.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const STATUSES = [ 'delivered', 'pending', 'retrying', 'failed' ];

    /**
     * The events a subscription may listen to, as select options: every
     * event in `artisanpack.ecommerce.webhooks.events` by wire name, plus
     * `*` for all events. Values already on a subscription that are not in
     * the config stay selectable so editing does not drop them.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $current  Events already selected.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function eventOptions( array $current = [] ): array
    {
        $options = [ WebhookSubscription::ALL_EVENTS => [ 'id' => WebhookSubscription::ALL_EVENTS, 'name' => __( 'All events' ) ] ];

        foreach ( (array) config( 'artisanpack.ecommerce.webhooks.events', [] ) as $event ) {
            if ( is_string( $event ) && '' !== $event ) {
                $name             = WebhookPayloadFactory::eventName( $event );
                $options[ $name ] = [ 'id' => $name, 'name' => $name ];
            }
        }

        foreach ( $current as $event ) {
            if ( is_string( $event ) && '' !== $event && ! isset( $options[ $event ] ) ) {
                $options[ $event ] = [ 'id' => $event, 'name' => $event ];
            }
        }

        return array_values( $options );
    }

    /**
     * How an event is shown.
     *
     * @since 1.0.0
     *
     * @param  string  $event  Wire name or `*`.
     *
     * @return string
     */
    public static function eventLabel( string $event ): string
    {
        return WebhookSubscription::ALL_EVENTS === $event ? __( 'All events' ) : $event;
    }

    /**
     * A new signing secret, in the engine's `whsec_` format.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function newSecret(): string
    {
        return 'whsec_' . Str::random( 48 );
    }

    /**
     * The consecutive failures after which the engine disables a subscription.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public static function disableAfter(): int
    {
        return max( 1, (int) config( 'artisanpack.ecommerce.webhooks.disable_after_failures', 10 ) );
    }

    /**
     * Whether the engine (rather than a person) switched the subscription off.
     *
     * @since 1.0.0
     *
     * @param  WebhookSubscription  $subscription  Subscription.
     *
     * @return bool
     */
    public static function disabledByEngine( WebhookSubscription $subscription ): bool
    {
        return ! $subscription->is_active && (int) $subscription->consecutive_failures >= self::disableAfter();
    }

    /**
     * A delivery's status.
     *
     * @since 1.0.0
     *
     * @param  WebhookDelivery  $delivery  Delivery.
     *
     * @return string One of {@see self::STATUSES}.
     */
    public static function status( WebhookDelivery $delivery ): string
    {
        return match ( true ) {
            null !== $delivery->delivered_at  => 'delivered',
            null === $delivery->next_retry_at => 'failed',
            0 === (int) $delivery->attempts   => 'pending',
            default                           => 'retrying',
        };
    }

    /**
     * Status filter options.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function statusOptions(): array
    {
        return array_map(
            static fn ( string $status ): array => [ 'id' => $status, 'name' => self::statusLabel( $status ) ],
            self::STATUSES,
        );
    }

    /**
     * A status's label.
     *
     * @since 1.0.0
     *
     * @param  string  $status  Status.
     *
     * @return string
     */
    public static function statusLabel( string $status ): string
    {
        return match ( $status ) {
            'delivered' => __( 'Delivered' ),
            'pending'   => __( 'Pending' ),
            'retrying'  => __( 'Retrying' ),
            default     => __( 'Failed' ),
        };
    }

    /**
     * A status's badge class.
     *
     * @since 1.0.0
     *
     * @param  string  $status  Status.
     *
     * @return string
     */
    public static function statusClass( string $status ): string
    {
        return match ( $status ) {
            'delivered' => 'badge-success',
            'pending'   => 'badge-ghost',
            'retrying'  => 'badge-warning',
            default     => 'badge-error',
        };
    }

    /**
     * Limits a deliveries query to one status.
     *
     * @since 1.0.0
     *
     * @param  Builder  $query   Deliveries query.
     * @param  string   $status  Status.
     *
     * @return void
     */
    public static function whereStatus( Builder $query, string $status ): void
    {
        match ( $status ) {
            'delivered' => $query->whereNotNull( $query->qualifyColumn( 'delivered_at' ) ),
            'failed'    => $query->whereNull( $query->qualifyColumn( 'delivered_at' ) )->whereNull( $query->qualifyColumn( 'next_retry_at' ) ),
            'pending'   => $query->whereNull( $query->qualifyColumn( 'delivered_at' ) )->whereNotNull( $query->qualifyColumn( 'next_retry_at' ) )->where( $query->qualifyColumn( 'attempts' ), 0 ),
            'retrying'  => $query->whereNull( $query->qualifyColumn( 'delivered_at' ) )->whereNotNull( $query->qualifyColumn( 'next_retry_at' ) )->where( $query->qualifyColumn( 'attempts' ), '>', 0 ),
            default     => null,
        };
    }

    /**
     * A date and time for the webhook screens, or an empty string.
     *
     * @since 1.0.0
     *
     * @param  DateTimeInterface|null  $date  Date.
     *
     * @return string
     */
    public static function dateTime( ?DateTimeInterface $date ): string
    {
        return LocalizedDate::format( $date, __( 'M j, Y g:i A' ) );
    }
}
