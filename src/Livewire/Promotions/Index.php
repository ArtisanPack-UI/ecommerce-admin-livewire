<?php

/**
 * Promotions index screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions;

use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\Ecommerce\Registries\PromotionSourceRegistry;
use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithResourceTable;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\PromotionsQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * The promotions table (spec §7.5): name, source type, state, window, uses
 * against the limit, priority, and whether the promotion is exclusive.
 *
 * Bulk actions switch promotions on or off (to end one early) and delete
 * them. A promotion that has been used on an order keeps its usage rows as
 * the discount audit trail, so it is switched off instead of deleted.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Index extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithActionToken;
    use WithResourceTable;

    /**
     * Authorizes the screen.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->authorizeTable();
    }

    /**
     * A source type's label.
     *
     * @since 1.0.0
     *
     * @param  string  $source  Source key.
     *
     * @return string
     */
    public static function sourceLabel( string $source ): string
    {
        $registry = app( PromotionSourceRegistry::class );

        return $registry->has( $source ) ? (string) ( $registry->meta( $source )['label'] ?? Str::headline( $source ) ) : Str::headline( $source );
    }

    /**
     * Every registered source type as select options.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function sourceOptions(): array
    {
        return array_map(
            static fn ( string $key ): array => [ 'id' => $key, 'name' => self::sourceLabel( $key ) ],
            app( PromotionSourceRegistry::class )->keys(),
        );
    }

    /**
     * A state's label.
     *
     * @since 1.0.0
     *
     * @param  string  $state  One of {@see PromotionsQuery::STATES}.
     *
     * @return string
     */
    public static function stateLabel( string $state ): string
    {
        return match ( $state ) {
            'active'    => __( 'Active' ),
            'scheduled' => __( 'Scheduled' ),
            'expired'   => __( 'Expired' ),
            'disabled'  => __( 'Disabled' ),
            default     => Str::headline( $state ),
        };
    }

    /**
     * The window a promotion runs in, as text.
     *
     * @since 1.0.0
     *
     * @param  Promotion  $promotion  The promotion.
     *
     * @return string
     */
    public static function windowLabel( Promotion $promotion ): string
    {
        $start = null === $promotion->starts_at ? null : LocalizedDate::format( $promotion->starts_at );
        $end   = null === $promotion->ends_at ? null : LocalizedDate::format( $promotion->ends_at );

        return match ( true ) {
            null === $start && null === $end => __( 'Always' ),
            null === $end                    => __( 'From :start', [ 'start' => $start ] ),
            null === $start                  => __( 'Until :end', [ 'end' => $end ] ),
            default                          => __( ':start – :end', [ 'start' => $start, 'end' => $end ] ),
        };
    }

    /**
     * Uses against the total limit, as text.
     *
     * @since 1.0.0
     *
     * @param  Promotion  $promotion  The promotion.
     *
     * @return string
     */
    public static function usesLabel( Promotion $promotion ): string
    {
        return null === $promotion->usage_limit_total
            ? __( ':used (no limit)', [ 'used' => (int) $promotion->times_used ] )
            : __( ':used of :limit', [ 'used' => (int) $promotion->times_used, 'limit' => (int) $promotion->usage_limit_total ] );
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $createRoute = AdminNav::ROUTE_PREFIX . 'promotions.create';

        return view( 'ecommerce-admin::livewire.promotions.index', $this->resourceTableData() + [
            'createUrl' => Route::has( $createRoute ) && $this->canEcommerce( 'create', Promotion::class ) ? route( $createRoute ) : null,
        ] );
    }

    /**
     * Switches the selected promotions on or off.
     *
     * @since 1.0.0
     *
     * @param  Builder<Promotion>  $selection  Selected promotions.
     * @param  bool                $active     The new switch state.
     *
     * @return string
     */
    protected function setActive( Builder $selection, bool $active ): string
    {
        $changed = 0;
        $denied  = 0;

        DB::transaction( function () use ( $selection, $active, &$changed, &$denied ): void {
            foreach ( ( clone $selection )->reorder()->get() as $promotion ) {
                if ( ! $this->canEcommerce( 'update', $promotion ) ) {
                    ++$denied;
                    continue;
                }

                if ( $promotion->is_active !== $active ) {
                    $promotion->forceFill( [ 'is_active' => $active ] )->save();
                    ++$changed;
                }
            }
        } );

        return self::withDeniedNote( $active
            ? trans_choice( ':count promotion switched on.|:count promotions switched on.', $changed, [ 'count' => $changed ] )
            : trans_choice( ':count promotion switched off.|:count promotions switched off.', $changed, [ 'count' => $changed ] ), $denied );
    }

    /**
     * Deletes the selected promotions that have never been used.
     *
     * @since 1.0.0
     *
     * @param  Builder<Promotion>  $selection  Selected promotions.
     *
     * @return string|null
     */
    protected function deleteSelection( Builder $selection ): ?string
    {
        $deleted = 0;
        $kept    = 0;
        $denied  = 0;

        DB::transaction( function () use ( $selection, &$deleted, &$kept, &$denied ): void {
            foreach ( ( clone $selection )->reorder()->withExists( 'usages' )->get() as $promotion ) {
                if ( ! $this->canEcommerce( 'delete', $promotion ) ) {
                    ++$denied;
                    continue;
                }

                if ( $promotion->usages_exists ) {
                    ++$kept;

                    continue;
                }

                $promotion->delete();
                ++$deleted;
            }
        } );

        if ( $kept > 0 ) {
            $this->toastWarning(
                trans_choice( ':count promotion was kept.|:count promotions were kept.', $kept, [ 'count' => $kept ] ),
                __( 'Promotions used on orders keep their history. Switch them off instead.' ),
            );
        }

        if ( 0 === $deleted && 0 === $denied ) {
            return null;
        }

        return self::withDeniedNote( trans_choice( ':count promotion deleted.|:count promotions deleted.', $deleted, [ 'count' => $deleted ] ), $denied );
    }

    /**
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeTable(): void
    {
        $this->authorizeEcommerce( 'viewAny', Promotion::class );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableScreen(): string
    {
        return 'promotions';
    }

    /**
     * @since 1.0.0
     *
     * @return ResourceQuery
     */
    protected function tableQuery(): ResourceQuery
    {
        return new PromotionsQuery();
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableCaption(): string
    {
        return __( 'Promotions' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableColumns(): array
    {
        $cells = 'ecommerce-admin::livewire.promotions.cells.';

        return [
            [
                'key'      => 'name',
                'label'    => __( 'Name' ),
                'sortable' => true,
                'view'     => $cells . 'name',
                'export'   => static fn ( Promotion $promotion ): string => (string) $promotion->name,
            ],
            [
                'key'   => 'source',
                'label' => __( 'Source' ),
                'value' => static fn ( Promotion $promotion ): string => self::sourceLabel( (string) $promotion->source_type ),
            ],
            [
                'key'    => 'state',
                'label'  => __( 'State' ),
                'view'   => $cells . 'state',
                'export' => static fn ( Promotion $promotion ): string => PromotionsQuery::state( $promotion ),
            ],
            [
                'key'      => 'window',
                'label'    => __( 'Runs' ),
                'sortable' => true,
                'value'    => static fn ( Promotion $promotion ): string => self::windowLabel( $promotion ),
            ],
            [
                'key'      => 'uses',
                'label'    => __( 'Uses' ),
                'sortable' => true,
                'value'    => static fn ( Promotion $promotion ): string => self::usesLabel( $promotion ),
                'export'   => static fn ( Promotion $promotion ): int => (int) $promotion->times_used,
            ],
            [
                'key'      => 'priority',
                'label'    => __( 'Priority' ),
                'sortable' => true,
                'class'    => 'text-end tabular-nums',
                'value'    => static fn ( Promotion $promotion ): string => (string) (int) $promotion->priority,
                'export'   => static fn ( Promotion $promotion ): int => (int) $promotion->priority,
            ],
            [
                'key'    => 'exclusive',
                'label'  => __( 'Exclusive' ),
                'value'  => static fn ( Promotion $promotion ): string => $promotion->is_exclusive ? __( 'Yes' ) : __( 'No' ),
            ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableFilters(): array
    {
        return [
            [
                'key'     => 'state',
                'label'   => __( 'State' ),
                'type'    => 'select',
                'options' => array_map( static fn ( string $state ): array => [ 'id' => $state, 'name' => self::stateLabel( $state ) ], PromotionsQuery::STATES ),
            ],
            [ 'key' => 'source_type', 'label' => __( 'Source' ), 'type' => 'select', 'options' => self::sourceOptions() ],
            [ 'key' => 'exclusive', 'label' => __( 'Exclusive' ), 'type' => 'boolean' ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableBulkActions(): array
    {
        return [
            [
                'key'     => 'activate',
                'label'   => __( 'Switch on' ),
                'icon'    => 'o-play',
                'ability' => 'promotion.update',
                'handler' => fn ( Builder $selection ): string => $this->setActive( $selection, true ),
            ],
            [
                'key'     => 'deactivate',
                'label'   => __( 'Switch off' ),
                'icon'    => 'o-pause',
                'ability' => 'promotion.update',
                'handler' => fn ( Builder $selection ): string => $this->setActive( $selection, false ),
            ],
            [
                'key'     => 'delete',
                'label'   => __( 'Delete' ),
                'icon'    => 'o-trash',
                'ability' => 'promotion.delete',
                'confirm' => __( 'Delete the selected promotions and their coupons? Promotions already used on orders are kept; switch those off instead.' ),
                'handler' => fn ( Builder $selection ): ?string => $this->deleteSelection( $selection ),
            ],
            $this->exportBulkAction(),
        ];
    }
}
