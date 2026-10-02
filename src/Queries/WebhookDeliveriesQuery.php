<?php

/**
 * Webhook deliveries query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Queries;

use ArtisanPackUI\Ecommerce\Models\WebhookDelivery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Webhooks;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Search, filters, and sorts for one subscription's deliveries log, newest
 * first.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class WebhookDeliveriesQuery extends ResourceQuery
{
    /**
     * @since 1.0.0
     *
     * @param  int  $subscriptionId  The subscription whose deliveries are listed.
     */
    public function __construct( private readonly int $subscriptionId )
    {
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public function sorts(): array
    {
        return [
            'created'   => 'webhook_deliveries.id',
            'attempts'  => 'webhook_deliveries.attempts',
            'delivered' => 'webhook_deliveries.delivered_at',
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array{0: string, 1: string}
     */
    public function defaultSort(): array
    {
        return [ 'created', 'desc' ];
    }

    /**
     * @since 1.0.0
     *
     * @return Builder
     */
    protected function baseQuery(): Builder
    {
        return WebhookDelivery::query()
            ->select( 'webhook_deliveries.*' )
            ->where( 'webhook_deliveries.subscription_id', $this->subscriptionId );
    }

    /**
     * @since 1.0.0
     *
     * @param  Builder  $query   Query.
     * @param  string   $search  Search term.
     *
     * @return void
     */
    protected function applySearch( Builder $query, string $search ): void
    {
        $query->where( static function ( Builder $where ) use ( $search ): void {
            static::orWhereContains( $where, 'webhook_deliveries.event', $search );
            static::orWhereContains( $where, 'webhook_deliveries.response_body', $search );
        } );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, Closure(Builder, mixed): mixed>
     */
    protected function filters(): array
    {
        return [
            'event'  => static fn ( Builder $query, mixed $value ) => $query->where( 'webhook_deliveries.event', (string) $value ),
            'status' => static fn ( Builder $query, mixed $value ) => Webhooks::whereStatus( $query, (string) $value ),
        ];
    }
}
