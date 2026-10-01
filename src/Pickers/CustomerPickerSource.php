<?php

/**
 * Customer picker source.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Pickers;

use ArtisanPackUI\Ecommerce\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Customers, searched by name and email.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class CustomerPickerSource extends EloquentPickerSource
{
    /**
     * {@inheritDoc}
     */
    public function ability(): string
    {
        return 'customer.viewAny';
    }

    /**
     * {@inheritDoc}
     */
    protected function query(): Builder
    {
        return Customer::query()->orderBy( 'last_name' )->orderBy( 'first_name' );
    }

    /**
     * {@inheritDoc}
     */
    protected function applySearch( Builder $query, string $like ): void
    {
        $this->whereLike( $query, 'first_name', $like );
        $this->whereLike( $query, 'last_name', $like, 'or' );
        $this->whereLike( $query, 'email', $like, 'or' );
    }

    /**
     * {@inheritDoc}
     */
    protected function toOption( Model $model ): array
    {
        $name = trim( $model->getAttribute( 'first_name' ) . ' ' . $model->getAttribute( 'last_name' ) );

        return [
            'id'          => $model->getKey(),
            'name'        => '' === $name ? (string) $model->getAttribute( 'email' ) : $name,
            'description' => '' === $name ? null : (string) $model->getAttribute( 'email' ),
        ];
    }
}
