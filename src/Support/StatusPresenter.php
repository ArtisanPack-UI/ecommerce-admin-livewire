<?php

/**
 * Status labels and colours.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use Illuminate\Support\Str;

/**
 * Turns the engine's status strings into a translated label and a badge colour.
 *
 * The engine stores statuses as plain strings. An unknown value (for example
 * one a satellite introduced) falls back to a headline-cased label and the
 * neutral colour; the `ap.ecommerceAdminLivewire.statusBadge` filter can
 * describe it properly.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class StatusPresenter
{
    /**
     * The status types this presenter knows.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const TYPES = [ 'system', 'payment', 'fulfillment', 'review', 'shipment' ];

    /**
     * The label and colour for a status value.
     *
     * @since 1.0.0
     *
     * @param  string  $type   One of {@see self::TYPES}.
     * @param  string  $value  The stored status.
     *
     * @return array{label: string, color: string}
     */
    public static function present( string $type, string $value ): array
    {
        $known = self::statuses( $type )[ $value ] ?? null;

        $presented = null === $known
            ? [ 'label' => Str::headline( $value ), 'color' => 'neutral' ]
            : [ 'label' => $known[0], 'color' => $known[1] ];

        return (array) applyFilters( 'ap.ecommerceAdminLivewire.statusBadge', $presented, $type, $value );
    }

    /**
     * The visually hidden prefix that names the status type, e.g. "Payment status:".
     *
     * @since 1.0.0
     *
     * @param  string  $type  The status type.
     *
     * @return string
     */
    public static function typeLabel( string $type ): string
    {
        return match ( $type ) {
            'system'      => __( 'Order status:' ),
            'payment'     => __( 'Payment status:' ),
            'fulfillment' => __( 'Fulfillment status:' ),
            'review'      => __( 'Review status:' ),
            'shipment'    => __( 'Shipment status:' ),
            'substatus'   => __( 'Sub-status:' ),
            default       => __( 'Status:' ),
        };
    }

    /**
     * The known values for a type, as `value => [ label, colour ]`.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The status type.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function statuses( string $type ): array
    {
        return match ( $type ) {
            'system' => [
                'pending'    => [ __( 'Pending' ), 'warning' ],
                'processing' => [ __( 'Processing' ), 'info' ],
                'complete'   => [ __( 'Complete' ), 'success' ],
                'cancelled'  => [ __( 'Cancelled' ), 'neutral' ],
                'refunded'   => [ __( 'Refunded' ), 'neutral' ],
                'failed'     => [ __( 'Failed' ), 'error' ],
            ],
            'payment' => [
                'pending'            => [ __( 'Pending' ), 'warning' ],
                'paid'               => [ __( 'Paid' ), 'success' ],
                'partially_refunded' => [ __( 'Partially refunded' ), 'info' ],
                'refunded'           => [ __( 'Refunded' ), 'info' ],
                'failed'             => [ __( 'Failed' ), 'error' ],
            ],
            'fulfillment' => [
                'unfulfilled' => [ __( 'Unfulfilled' ), 'warning' ],
                'partial'     => [ __( 'Partially fulfilled' ), 'info' ],
                'fulfilled'   => [ __( 'Fulfilled' ), 'success' ],
            ],
            'review' => [
                'pending'  => [ __( 'Pending' ), 'warning' ],
                'approved' => [ __( 'Approved' ), 'success' ],
                'rejected' => [ __( 'Rejected' ), 'error' ],
                'spam'     => [ __( 'Spam' ), 'neutral' ],
            ],
            'shipment' => [
                'pending'    => [ __( 'Pending' ), 'warning' ],
                'in_transit' => [ __( 'In transit' ), 'info' ],
                'delivered'  => [ __( 'Delivered' ), 'success' ],
                'exception'  => [ __( 'Exception' ), 'error' ],
            ],
            default => [],
        };
    }
}
