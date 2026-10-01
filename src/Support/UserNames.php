<?php

/**
 * User name lookup.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Names for the user ids the engine records as actors, issuers, and
 * authors, read from the host's user model in one query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class UserNames
{
    /**
     * Names by user id. Ids without a user (or without a name) are left out.
     *
     * @since 1.0.0
     *
     * @param  array<int, int|null>  $ids  User ids.
     *
     * @return array<int, string>
     */
    public static function for( array $ids ): array
    {
        $ids   = array_values( array_unique( array_filter( array_map( 'intval', array_filter( $ids, 'is_numeric' ) ) ) ) );
        $model = config( 'auth.providers.users.model' );

        if ( [] === $ids || ! is_string( $model ) || ! class_exists( $model ) || ! is_subclass_of( $model, Model::class ) ) {
            return [];
        }

        try {
            $names = [];

            foreach ( $model::query()->whereKey( $ids )->get() as $user ) {
                $name = $user->getAttribute( 'name' );

                if ( is_string( $name ) && '' !== $name ) {
                    $names[ (int) $user->getKey() ] = $name;
                }
            }

            return $names;
        } catch ( Throwable ) {
            return [];
        }
    }

    /**
     * The display name for one user id: the name, "User #id" when the user
     * is gone, or "System" for no user.
     *
     * @since 1.0.0
     *
     * @param  int|null            $id     User id.
     * @param  array<int, string>  $names  Names from {@see self::for()}.
     *
     * @return string
     */
    public static function label( ?int $id, array $names ): string
    {
        if ( null === $id ) {
            return __( 'System' );
        }

        return $names[ $id ] ?? __( 'User #:id', [ 'id' => $id ] );
    }
}
