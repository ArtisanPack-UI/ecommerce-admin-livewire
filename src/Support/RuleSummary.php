<?php

/**
 * Plain-language rule summaries.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductCategory;
use ArtisanPackUI\Ecommerce\Models\ProductTag;
use ArtisanPackUI\Ecommerce\Models\ProductVariant;
use ArtisanPackUI\Ecommerce\Registries\ProductTypeRegistry;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;
use Throwable;

/**
 * Turns rule-builder rows into translated sentences (spec §7.5), e.g.
 * "10% off the cart when the subtotal is at least $50.00 and it is the
 * customer's first order."
 *
 * Core condition and action keys are described here. A satellite describes
 * its own keys through the `ap.ecommerceAdminLivewire.ruleBuilder.describe`
 * filter, which receives `( ?string $text, string $registry, string $type,
 * array $config )` and returns a lower-case phrase that reads in the middle
 * of a sentence ("the customer has 100 points"). Keys nobody describes fall
 * back to their registry label.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class RuleSummary
{
    /**
     * How many product names a phrase lists before "and N more".
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_NAMES = 3;

    /**
     * Summarizes a promotion: its actions, then when they apply.
     *
     * @since 1.0.0
     *
     * @param  array<int, array{type: string, config: array<string, mixed>}>  $conditions  Condition rows.
     * @param  array<int, array{type: string, config: array<string, mixed>}>  $actions     Action rows.
     *
     * @return string
     */
    public static function promotion( array $conditions, array $actions ): string
    {
        if ( [] === $actions ) {
            return __( 'No discount yet. Add an action to say what the promotion gives.' );
        }

        $what = self::join( self::phrases( 'promotion-action', $actions ), __( 'and' ) );

        if ( [] === $conditions ) {
            return self::sentence( __( ':actions on every cart.', [ 'actions' => $what ] ) );
        }

        return self::sentence( __( ':actions when :conditions.', [
            'actions'    => $what,
            'conditions' => self::join( self::phrases( 'promotion-condition', $conditions ), __( 'and' ) ),
        ] ) );
    }

    /**
     * Summarizes a set of conditions on their own (kanban routing rules).
     *
     * @since 1.0.0
     *
     * @param  array<int, array{type: string, config: array<string, mixed>}>  $conditions  Condition rows.
     *
     * @return string
     */
    public static function conditions( array $conditions ): string
    {
        if ( [] === $conditions ) {
            return __( 'Every order matches.' );
        }

        return __( 'Orders match when :conditions.', [
            'conditions' => self::join( self::phrases( 'promotion-condition', $conditions ), __( 'and' ) ),
        ] );
    }

    /**
     * Describes one row as a lower-case phrase.
     *
     * @since 1.0.0
     *
     * @param  string                $registry  A {@see ConfigFormRegistry::REGISTRIES} name.
     * @param  string                $type      The row's type key.
     * @param  array<string, mixed>  $config    The row's config.
     *
     * @return string
     */
    public static function describe( string $registry, string $type, array $config ): string
    {
        try {
            $text = applyFilters( 'ap.ecommerceAdminLivewire.ruleBuilder.describe', null, $registry, $type, $config );

            if ( is_string( $text ) && '' !== trim( $text ) ) {
                return trim( $text );
            }

            return match ( $registry ) {
                'promotion-condition' => self::condition( $type, $config ),
                'promotion-action'    => self::action( $type, $config ),
                default               => null,
            } ?? self::fallback( $registry, $type );
        } catch ( Throwable $exception ) {
            report( $exception );

            return self::fallback( $registry, $type );
        }
    }

    /**
     * Joins phrases as "a, b and c".
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $parts        Phrases.
     * @param  string              $conjunction  The translated "and" or "or".
     *
     * @return string
     */
    public static function join( array $parts, string $conjunction ): string
    {
        $parts = array_values( array_filter( $parts, static fn ( string $part ): bool => '' !== $part ) );

        if ( count( $parts ) < 2 ) {
            return $parts[0] ?? '';
        }

        $last = array_pop( $parts );

        return __( ':items :conjunction :last', [ 'items' => implode( ', ', $parts ), 'conjunction' => $conjunction, 'last' => $last ] );
    }

    /**
     * Describes a core promotion condition.
     *
     * @since 1.0.0
     *
     * @param  string                $type    Condition key.
     * @param  array<string, mixed>  $config  Config.
     *
     * @return string|null
     */
    private static function condition( string $type, array $config ): ?string
    {
        return match ( $type ) {
            'min-subtotal'               => __( 'the subtotal is at least :amount', [ 'amount' => self::money( $config['amount'] ?? null ) ] ),
            'cart-contains-product'      => self::containsProducts( $config ),
            'cart-contains-product-type' => match ( (string) ( $config['match'] ?? 'any' ) ) {
                'all'   => __( 'the cart contains each of these types: :types', [ 'types' => self::join( self::productTypes( $config['types'] ?? [] ), __( 'and' ) ) ] ),
                'only'  => __( 'the cart contains only items of type :types', [ 'types' => self::join( self::productTypes( $config['types'] ?? [] ), __( 'or' ) ) ] ),
                default => __( 'the cart contains an item of type :types', [ 'types' => self::join( self::productTypes( $config['types'] ?? [] ), __( 'or' ) ) ] ),
            },
            'customer-in-group'          => __( 'the customer is in :groups', [ 'groups' => self::join( self::strings( $config['groups'] ?? [] ), __( 'or' ) ) ?: __( 'a chosen group' ) ] ),
            'day-of-week'                => __( 'it is :days', [ 'days' => self::join( self::weekdays( $config['days'] ?? [] ), __( 'or' ) ) ?: __( 'a chosen day' ) ] ),
            'customer-first-order'       => __( 'it is the customer\'s first order' ),
            'cart-contains-category'     => self::containsNamed(
                'all' === ( $config['match'] ?? 'any' ),
                self::names( ProductCategory::class, $config['category_ids'] ?? [] ),
                false !== ( $config['include_descendants'] ?? true )
                    ? [ __( 'the cart contains an item from :names (or their sub-categories)' ), __( 'the cart contains items from each of :names (or their sub-categories)' ) ]
                    : [ __( 'the cart contains an item from :names' ), __( 'the cart contains items from each of :names' ) ],
            ),
            'cart-contains-tag'          => self::containsNamed(
                'all' === ( $config['match'] ?? 'any' ),
                self::names( ProductTag::class, $config['tag_ids'] ?? [] ),
                [ __( 'the cart contains an item tagged :names' ), __( 'the cart contains items tagged each of :names' ) ],
            ),
            'currency-is'                  => __( 'the cart is in :currencies', [ 'currencies' => self::join( self::strings( $config['currencies'] ?? [] ), __( 'or' ) ) ?: __( 'a chosen currency' ) ] ),
            'customer-lifetime-value-over' => __( 'the customer has spent more than :amount', [ 'amount' => self::money( $config['amount'] ?? null ) ] ),
            'date-range'                   => self::dateRange( $config ),
            'min-quantity'                 => __( 'the cart has at least :quantity of :items', [
                'quantity' => max( 1, (int) ( $config['quantity'] ?? 1 ) ),
                'items'    => self::items( $config['product_ids'] ?? [], $config['variant_ids'] ?? [], __( 'or' ) ),
            ] ),
            default                      => null,
        };
    }

    /**
     * "Contains" phrasing for named categories or tags.
     *
     * @since 1.0.0
     *
     * @param  bool                       $all      Whether every one must match.
     * @param  array<int, string>         $names    The names.
     * @param  array{0: string, 1: string} $phrases Any-phrase and all-phrase, each with `:names`.
     *
     * @return string
     */
    private static function containsNamed( bool $all, array $names, array $phrases ): string
    {
        return str_replace( ':names', self::join( $names, $all ? __( 'and' ) : __( 'or' ) ), $phrases[ $all ? 1 : 0 ] );
    }

    /**
     * Model names for ids, in order, capped at {@see self::MAX_NAMES}.
     *
     * @since 1.0.0
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model  Category or tag model.
     * @param  mixed                                               $ids    The ids.
     *
     * @return array<int, string>
     */
    private static function names( string $model, mixed $ids ): array
    {
        $ids = self::ids( $ids );

        if ( [] === $ids ) {
            return [ __( 'the chosen ones' ) ];
        }

        $found = $model::query()->whereKey( $ids )->pluck( 'name', 'id' );
        $names = array_map( static fn ( int $id ): string => (string) ( $found[ $id ] ?? '#' . $id ), $ids );

        if ( count( $names ) > self::MAX_NAMES ) {
            $more  = count( $names ) - self::MAX_NAMES;
            $names = [ ...array_slice( $names, 0, self::MAX_NAMES ), trans_choice( ':count more|:count more', $more, [ 'count' => $more ] ) ];
        }

        return $names;
    }

    /**
     * "It is between …" for a date range.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $config  The condition config.
     *
     * @return string
     */
    private static function dateRange( array $config ): string
    {
        $from  = is_string( $config['starts_on'] ?? null ) && '' !== $config['starts_on'] ? $config['starts_on'] : null;
        $until = is_string( $config['ends_on'] ?? null ) && '' !== $config['ends_on'] ? $config['ends_on'] : null;

        return match ( true ) {
            null !== $from && null !== $until => __( 'it is between :from and :until', [ 'from' => $from, 'until' => $until ] ),
            null !== $from                    => __( 'it is :from or later', [ 'from' => $from ] ),
            null !== $until                   => __( 'it is :until or earlier', [ 'until' => $until ] ),
            default                           => __( 'it is any day' ),
        };
    }

    /**
     * Describes a core promotion action.
     *
     * @since 1.0.0
     *
     * @param  string                $type    Action key.
     * @param  array<string, mixed>  $config  Config.
     *
     * @return string|null
     */
    private static function action( string $type, array $config ): ?string
    {
        return match ( $type ) {
            'percent-off-cart'    => __( ':percent off the cart', [ 'percent' => self::percent( $config['percent'] ?? null ) ] ),
            'fixed-off-cart'      => __( ':amount off the cart', [ 'amount' => self::money( $config['amount'] ?? null ) ] ),
            'percent-off-product' => __( ':percent off :items', [ 'percent' => self::percent( $config['percent'] ?? null ), 'items' => self::items( $config['product_ids'] ?? [], $config['variant_ids'] ?? [], __( 'and' ) ) ] ),
            'free-shipping'       => __( 'free shipping' ),
            'buy-x-get-y'         => self::buyXGetY( $config ),
            'add-free-item'       => trans_choice( 'a free :item|:count free :item', max( 1, (int) ( $config['quantity'] ?? 1 ) ), [
                'count' => max( 1, (int) ( $config['quantity'] ?? 1 ) ),
                'item'  => self::items( array_filter( [ $config['product_id'] ?? null ] ), array_filter( [ $config['variant_id'] ?? null ] ), __( 'or' ) ),
            ] ),
            'fixed-off-product'   => 'line' === ( $config['per'] ?? 'unit' )
                ? __( ':amount off each line of :items', [ 'amount' => self::money( $config['amount'] ?? null ), 'items' => self::items( $config['product_ids'] ?? [], $config['variant_ids'] ?? [], __( 'and' ) ) ] )
                : __( ':amount off each :items', [ 'amount' => self::money( $config['amount'] ?? null ), 'items' => self::items( $config['product_ids'] ?? [], $config['variant_ids'] ?? [], __( 'and' ) ) ] ),
            'tiered-discount'     => trans_choice( 'a tiered discount with :count tier|a tiered discount with :count tiers', count( (array) ( $config['tiers'] ?? [] ) ), [ 'count' => count( (array) ( $config['tiers'] ?? [] ) ) ] ),
            default               => null,
        };
    }

    /**
     * Describes `cart-contains-product`.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $config  Config.
     *
     * @return string
     */
    private static function containsProducts( array $config ): string
    {
        $all      = 'all' === ( $config['match'] ?? 'any' );
        $items    = self::items( $config['product_ids'] ?? [], $config['variant_ids'] ?? [], $all ? __( 'and' ) : __( 'or' ) );
        $quantity = max( 1, (int) ( $config['min_quantity'] ?? 1 ) );

        if ( $quantity > 1 ) {
            return __( 'the cart contains at least :quantity of :items', [ 'quantity' => $quantity, 'items' => $items ] );
        }

        return __( 'the cart contains :items', [ 'items' => $items ] );
    }

    /**
     * Describes `buy-x-get-y`: "buy 2, get 1 free".
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $config  Config.
     *
     * @return string
     */
    private static function buyXGetY( array $config ): string
    {
        $percent = is_numeric( $config['percent'] ?? null ) ? (float) $config['percent'] : 100.0;
        $reward  = $percent >= 100 ? __( 'free' ) : __( ':percent off', [ 'percent' => self::percent( $percent ) ] );
        $buy     = self::items( $config['buy_product_ids'] ?? [], $config['buy_variant_ids'] ?? [], __( 'or' ) );

        return __( 'buy :buy of :items, get :get :reward', [
            'buy'    => max( 1, (int) ( $config['buy_quantity'] ?? 1 ) ),
            'get'    => max( 1, (int) ( $config['get_quantity'] ?? 1 ) ),
            'items'  => $buy,
            'reward' => $reward,
        ] );
    }

    /**
     * The phrases for a list of rows.
     *
     * @since 1.0.0
     *
     * @param  string                                                         $registry  Registry name.
     * @param  array<int, array{type: string, config: array<string, mixed>}>  $rows      Rows.
     *
     * @return array<int, string>
     */
    private static function phrases( string $registry, array $rows ): array
    {
        return array_map( static fn ( array $row ): string => self::describe( $registry, (string) ( $row['type'] ?? '' ), (array) ( $row['config'] ?? [] ) ), $rows );
    }

    /**
     * Product and variant names, shortened to "a, b, c and 2 more".
     *
     * @since 1.0.0
     *
     * @param  mixed   $productIds   Product ids.
     * @param  mixed   $variantIds   Variant ids.
     * @param  string  $conjunction  The translated "and" or "or".
     *
     * @return string
     */
    private static function items( mixed $productIds, mixed $variantIds, string $conjunction ): string
    {
        $products = self::ids( $productIds );
        $variants = self::ids( $variantIds );
        $names    = [];

        if ( [] !== $products ) {
            $found = Product::query()->whereKey( $products )->pluck( 'name', 'id' );

            foreach ( $products as $id ) {
                $names[] = (string) ( $found[ $id ] ?? __( 'product #:id', [ 'id' => $id ] ) );
            }
        }

        if ( [] !== $variants ) {
            $found = ProductVariant::query()->whereKey( $variants )->with( 'product:id,name' )->get()->keyBy( 'id' );

            foreach ( $variants as $id ) {
                $variant = $found[ $id ] ?? null;
                $names[] = null === $variant
                    ? __( 'variant #:id', [ 'id' => $id ] )
                    : trim( ( $variant->product?->name ?? '' ) . ' ' . ( $variant->name ?: $variant->sku ) );
            }
        }

        if ( [] === $names ) {
            return __( 'any product' );
        }

        if ( count( $names ) > self::MAX_NAMES ) {
            $more  = count( $names ) - self::MAX_NAMES;
            $names = [ ...array_slice( $names, 0, self::MAX_NAMES ), trans_choice( ':count more|:count more', $more, [ 'count' => $more ] ) ];
        }

        return self::join( $names, $conjunction );
    }

    /**
     * Product type labels.
     *
     * @since 1.0.0
     *
     * @param  mixed  $types  Type keys.
     *
     * @return array<int, string>
     */
    private static function productTypes( mixed $types ): array
    {
        $registry = app( ProductTypeRegistry::class );

        return array_map(
            static fn ( string $type ): string => $registry->has( $type ) ? mb_strtolower( $registry->get( $type )->label() ) : $type,
            self::strings( $types ),
        ) ?: [ __( 'chosen' ) ];
    }

    /**
     * Weekday names for ISO day numbers (1 = Monday).
     *
     * @since 1.0.0
     *
     * @param  mixed  $days  Day numbers.
     *
     * @return array<int, string>
     */
    private static function weekdays( mixed $days ): array
    {
        $names = [
            1 => __( 'Monday' ),
            2 => __( 'Tuesday' ),
            3 => __( 'Wednesday' ),
            4 => __( 'Thursday' ),
            5 => __( 'Friday' ),
            6 => __( 'Saturday' ),
            7 => __( 'Sunday' ),
        ];

        return array_values( array_filter( array_map( static fn ( int $day ): ?string => $names[ $day ] ?? null, self::ids( $days ) ) ) );
    }

    /**
     * A minor-unit amount in the store currency, or a placeholder.
     *
     * @since 1.0.0
     *
     * @param  mixed  $amount  Amount in minor units.
     *
     * @return string
     */
    private static function money( mixed $amount ): string
    {
        // A per-currency amount: `{ "USD": 500, "EUR": 450 }`.
        if ( is_array( $amount ) ) {
            $parts = [];

            foreach ( $amount as $currency => $minor ) {
                if ( is_string( $currency ) && 1 === preg_match( '/^[A-Za-z]{3}$/', $currency ) && is_numeric( $minor ) ) {
                    $parts[] = MoneyFormatter::format( (int) $minor, strtoupper( $currency ) );
                }
            }

            return [] === $parts ? __( 'an amount' ) : implode( ' / ', $parts );
        }

        return is_numeric( $amount ) ? MoneyFormatter::format( (int) $amount, StoreCurrencies::base() ) : __( 'an amount' );
    }

    /**
     * A percentage, or a placeholder.
     *
     * @since 1.0.0
     *
     * @param  mixed  $percent  Percent (0–100).
     *
     * @return string
     */
    private static function percent( mixed $percent ): string
    {
        if ( ! is_numeric( $percent ) ) {
            return __( 'a percentage' );
        }

        return __( ':value%', [ 'value' => rtrim( rtrim( number_format( (float) $percent, 2, '.', '' ), '0' ), '.' ) ] );
    }

    /**
     * Positive integer ids, deduplicated, in order.
     *
     * @since 1.0.0
     *
     * @param  mixed  $values  Raw values.
     *
     * @return array<int, int>
     */
    private static function ids( mixed $values ): array
    {
        return array_values( array_unique( array_filter( array_map( 'intval', array_filter( (array) $values, 'is_numeric' ) ), static fn ( int $id ): bool => $id > 0 ) ) );
    }

    /**
     * Non-blank strings.
     *
     * @since 1.0.0
     *
     * @param  mixed  $values  Raw values.
     *
     * @return array<int, string>
     */
    private static function strings( mixed $values ): array
    {
        return array_values( array_filter( array_map( static fn ( mixed $value ): string => trim( (string) $value ), array_filter( (array) $values, 'is_scalar' ) ), static fn ( string $value ): bool => '' !== $value ) );
    }

    /**
     * A key's registry label in quotes, for keys nobody describes.
     *
     * @since 1.0.0
     *
     * @param  string  $registry  Registry name.
     * @param  string  $type      Key.
     *
     * @return string
     */
    private static function fallback( string $registry, string $type ): string
    {
        $class = ConfigFormRegistry::REGISTRIES[ $registry ] ?? null;

        try {
            $label = null !== $class && app( $class )->has( $type ) ? (string) app( $class )->get( $type )->label() : $type;
        } catch ( Throwable ) {
            $label = $type;
        }

        return __( '":label"', [ 'label' => $label ] );
    }

    /**
     * Capitalizes the first letter of a sentence.
     *
     * @since 1.0.0
     *
     * @param  string  $text  Sentence.
     *
     * @return string
     */
    private static function sentence( string $text ): string
    {
        return mb_strtoupper( mb_substr( $text, 0, 1 ) ) . mb_substr( $text, 1 );
    }
}
