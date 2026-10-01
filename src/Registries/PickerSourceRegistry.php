<?php

/**
 * Picker source registry.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Registries;

use ArtisanPackUI\EcommerceAdminLivewire\Pickers\PickerSource;

/**
 * Maps a picker type (`product`, `variant`, `customer`, …) to its source.
 *
 * The core registers products, variants, and customers. Categories and tags
 * register once the engine stores them (engine issue #139); until then their
 * pickers render a notice. Satellites register their own types the same way.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class PickerSourceRegistry
{
    /**
     * The registered sources.
     *
     * @since 1.0.0
     *
     * @var array<string, PickerSource>
     */
    private array $sources = [];

    /**
     * Registers (or replaces) a source.
     *
     * @since 1.0.0
     *
     * @param  string        $type    The picker type.
     * @param  PickerSource  $source  The source.
     *
     * @return void
     */
    public function register( string $type, PickerSource $source ): void
    {
        $this->sources[ $type ] = $source;
    }

    /**
     * Whether a type is registered.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The picker type.
     *
     * @return bool
     */
    public function has( string $type ): bool
    {
        return isset( $this->sources[ $type ] );
    }

    /**
     * The source for a type, or null.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The picker type.
     *
     * @return PickerSource|null
     */
    public function get( string $type ): ?PickerSource
    {
        return $this->sources[ $type ] ?? null;
    }

    /**
     * Every registered source.
     *
     * @since 1.0.0
     *
     * @return array<string, PickerSource>
     */
    public function all(): array
    {
        return $this->sources;
    }
}
