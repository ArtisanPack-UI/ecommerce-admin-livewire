<?php

/**
 * Picker source contract.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Pickers;

/**
 * Supplies the options for one kind of picker (products, customers, …).
 *
 * Options are `[ 'id' => …, 'name' => …, 'description' => … ]` arrays, the
 * shape `x-artisanpack-choices` reads.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
interface PickerSource
{
    /**
     * The most ids `find()` is asked to label at once. The selection comes
     * from the client, so it is capped before it reaches a `whereIn`.
     *
     * @since 2.0.0
     *
     * @var int
     */
    public const MAX_IDS = 200;

    /**
     * The `{resource}.{action}` ability needed to search this source.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function ability(): string;

    /**
     * Options matching a search term.
     *
     * @since 1.0.0
     *
     * @param  string  $term   The search term; empty returns the first options.
     * @param  int     $limit  The maximum number of options.
     *
     * @return array<int, array{id: int|string, name: string, description: string|null}>
     */
    public function search( string $term, int $limit ): array;

    /**
     * Options for the given ids, so selected values keep their labels.
     *
     * Callers pass at most {@see self::MAX_IDS} ids; implementations should
     * cap the list as well.
     *
     * @since 1.0.0
     *
     * @param  array<int, int|string>  $ids  The selected ids.
     *
     * @return array<int, array{id: int|string, name: string, description: string|null}>
     */
    public function find( array $ids ): array;
}
