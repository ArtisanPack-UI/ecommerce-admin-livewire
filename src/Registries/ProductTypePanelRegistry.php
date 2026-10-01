<?php

/**
 * Product-type panel registry.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Registries;

use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\ProductTypePanel;
use InvalidArgumentException;
use Livewire\Livewire;
use Throwable;

/**
 * Maps a product type key to the Livewire panel the product form mounts for
 * that type (spec §8.5).
 *
 * A panel is a Livewire component extending {@see ProductTypePanel}. The
 * form binds its state with `wire:model`, validates it with the panel's
 * `rules()`, and saves it with the panel's `save()` in the same transaction
 * as the product, so one Save button covers every tab.
 *
 * Satellites register their own types' panels from a service provider:
 *
 * ```php
 * app( ProductTypePanelRegistry::class )->register( 'subscription', 'my-subscription-panel' );
 * ```
 *
 * Registering `null` says the type needs no panel (core `simple`). A type
 * that isn't registered at all gets a notice on the form instead of a panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class ProductTypePanelRegistry
{
    /**
     * Livewire component (alias or class) per type key; null for "no panel".
     *
     * @since 1.0.0
     *
     * @var array<string, string|null>
     */
    private array $panels = [];

    /**
     * Resolved panel classes.
     *
     * @since 1.0.0
     *
     * @var array<string, class-string<ProductTypePanel>>
     */
    private array $classes = [];

    /**
     * Registers (or replaces) the panel for a product type.
     *
     * @since 1.0.0
     *
     * @param  string       $typeKey             Product type key.
     * @param  string|null  $livewireComponent   Livewire alias or class extending ProductTypePanel; null for none.
     *
     * @throws InvalidArgumentException When the key is empty.
     *
     * @return void
     */
    public function register( string $typeKey, ?string $livewireComponent ): void
    {
        if ( '' === trim( $typeKey ) ) {
            throw new InvalidArgumentException( 'A product-type panel needs a type key.' );
        }

        $this->panels[ $typeKey ] = $livewireComponent;
        unset( $this->classes[ $typeKey ] );
    }

    /**
     * Removes a type's registration.
     *
     * @since 1.0.0
     *
     * @param  string  $typeKey  Product type key.
     *
     * @return void
     */
    public function forget( string $typeKey ): void
    {
        unset( $this->panels[ $typeKey ], $this->classes[ $typeKey ] );
    }

    /**
     * Whether the type is registered (with a panel or explicitly without).
     *
     * @since 1.0.0
     *
     * @param  string  $typeKey  Product type key.
     *
     * @return bool
     */
    public function has( string $typeKey ): bool
    {
        return array_key_exists( $typeKey, $this->panels );
    }

    /**
     * The Livewire component registered for the type, or null.
     *
     * @since 1.0.0
     *
     * @param  string  $typeKey  Product type key.
     *
     * @return string|null
     */
    public function component( string $typeKey ): ?string
    {
        return $this->panels[ $typeKey ] ?? null;
    }

    /**
     * The panel class for the type, or null when there is none.
     *
     * @since 1.0.0
     *
     * @param  string  $typeKey  Product type key.
     *
     * @throws InvalidArgumentException When the component can't be resolved or isn't a ProductTypePanel.
     *
     * @return class-string<ProductTypePanel>|null
     */
    public function panelClass( string $typeKey ): ?string
    {
        $component = $this->component( $typeKey );

        if ( null === $component ) {
            return null;
        }

        if ( isset( $this->classes[ $typeKey ] ) ) {
            return $this->classes[ $typeKey ];
        }

        try {
            $class = class_exists( $component ) ? $component : $this->resolveAlias( $component );
        } catch ( Throwable $exception ) {
            throw new InvalidArgumentException(
                sprintf( 'The "%s" product-type panel "%s" could not be resolved.', $typeKey, $component ),
                0,
                $exception,
            );
        }

        if ( ! is_subclass_of( $class, ProductTypePanel::class ) ) {
            throw new InvalidArgumentException( sprintf( 'The "%s" product-type panel must extend %s.', $typeKey, ProductTypePanel::class ) );
        }

        return $this->classes[ $typeKey ] = $class;
    }

    /**
     * Every registration.
     *
     * @since 1.0.0
     *
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return $this->panels;
    }

    /**
     * The class behind a Livewire alias (throws when Livewire can't resolve it).
     *
     * @since 1.0.0
     *
     * @param  string  $alias  Livewire component name.
     *
     * @return string
     */
    private function resolveAlias( string $alias ): string
    {
        return Livewire::new( $alias )::class;
    }
}
