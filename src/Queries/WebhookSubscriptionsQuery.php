<?php

/**
 * Webhook subscriptions query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Queries;

use ArtisanPackUI\Ecommerce\Models\WebhookSubscription;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Search, filters, and sorts for the webhook subscriptions table.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class WebhookSubscriptionsQuery extends ResourceQuery
{
    /**
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public function sorts(): array
    {
        return [
            'name'         => 'webhook_subscriptions.name',
            'last_success' => 'webhook_subscriptions.last_success_at',
            'last_failure' => 'webhook_subscriptions.last_failure_at',
            'failures'     => 'webhook_subscriptions.consecutive_failures',
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array{0: string, 1: string}
     */
    public function defaultSort(): array
    {
        return [ 'name', 'asc' ];
    }

    /**
     * @since 1.0.0
     *
     * @return Builder
     */
    protected function baseQuery(): Builder
    {
        return WebhookSubscription::query()->select( 'webhook_subscriptions.*' );
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
            static::orWhereContains( $where, 'webhook_subscriptions.name', $search );
            static::orWhereContains( $where, 'webhook_subscriptions.url', $search );
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
            'active' => static fn ( Builder $query, mixed $value ) => $query->where( 'webhook_subscriptions.is_active', '1' === (string) $value ),
        ];
    }
}
