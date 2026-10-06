<?php

/**
 * Admin refinements of the core config form schemas.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Http\Requests\Api\V1\WebhookSubscriptionRequest;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;
use Closure;
use Illuminate\Support\Facades\Validator;

/**
 * Extra form rules for core engine entries.
 *
 * Every engine 1.0 built-in describes its own config (`DescribesConfig`), so
 * its form comes from the engine. A few cross-field checks the engine schema
 * can't express are added here, so the admin catches them before saving.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class ConfigSchemas
{
    /**
     * Registers the refinements.
     *
     * @since 1.0.0
     *
     * @param  ConfigFormRegistry  $registry  The registry.
     *
     * @return void
     */
    public static function register( ConfigFormRegistry $registry ): void
    {
        foreach ( self::refinements() as $name => $entries ) {
            foreach ( $entries as $key => $rules ) {
                $registry->refine( $name, $key, $rules );
            }
        }
    }

    /**
     * Extra rules per registry entry, keyed by field path (`tiers.percent`
     * is the `percent` column of the `tiers` repeater).
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, array<string, array<int, Closure|string>>>>
     */
    public static function refinements(): array
    {
        return [
            'promotion-action' => [
                // Each tier gives a percent or an amount.
                'tiered-discount' => [
                    'tiers.percent' => [ 'required_without:@amount' ],
                    'tiers.amount'  => [ 'required_without:@percent' ],
                ],
            ],
            'kanban-trigger'   => [
                // The same SSRF check as webhook subscriptions.
                'webhook' => [
                    'url' => [ self::webhookUrlRule() ],
                ],
            ],
        ];
    }

    /**
     * A rule applying the engine's webhook subscription URL rules (allowed
     * schemes, and a host that resolves to a public address), read when it
     * runs so it follows the current config.
     *
     * @since 1.0.0
     *
     * @return Closure(string, mixed, Closure): void
     */
    public static function webhookUrlRule(): Closure
    {
        return static function ( string $attribute, mixed $value, Closure $fail ): void {
            if ( null === $value || '' === $value ) {
                return;
            }

            $validator = Validator::make( [ 'url' => $value ], [ 'url' => WebhookSubscriptionRequest::baseRules()['url'] ], [], [ 'url' => __( 'URL' ) ] );

            if ( $validator->fails() ) {
                $fail( (string) $validator->errors()->first( 'url' ) );
            }
        };
    }
}
