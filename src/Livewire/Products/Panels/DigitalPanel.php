<?php

/**
 * Digital product panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels;

use ArtisanPackUI\Ecommerce\Models\DigitalFile;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Services\DigitalFileService;
use ArtisanPackUI\Ecommerce\Services\ProductService;
use ArtisanPackUI\EcommerceAdminLivewire\Support\DigitalDisks;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;

/**
 * The `digital` type panel (spec §7.2): the files a purchase unlocks, the
 * download limit and expiry, and license-key issuance.
 *
 * - Files have a label, version, and streaming-only flag, and live either in
 *   the media library (when installed) or at a path on one of the engine's
 *   allowed digital disks. Bumping a saved file's version goes through
 *   `DigitalFileService::update()`, which tells past buyers.
 * - The download limit and expiry are stored on the product
 *   (`meta.digital.download_limit`, `meta.digital.download_expiry_days`);
 *   empty uses the store default and 0 means no cap.
 * - License keys are issued when `meta.licensing.enabled` is on, with an
 *   optional activation limit and lifetime.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class DigitalPanel extends ProductTypePanel
{
    /**
     * Media-library context prefix for file rows.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const MEDIA_CONTEXT = 'ecommerce-digital-file-';

    /**
     * @since 1.0.0
     *
     * @return string
     */
    public static function label(): string
    {
        return __( 'Downloads' );
    }

    /**
     * @since 1.0.0
     *
     * @param  Product|null  $product  Product.
     *
     * @return array<string, mixed>
     */
    public static function initialState( ?Product $product ): array
    {
        $digital   = (array) ( $product?->meta['digital'] ?? [] );
        $licensing = (array) ( $product?->meta['licensing'] ?? [] );

        return [
            'files'                   => null === $product ? [] : DigitalFile::query()
                ->where( 'product_id', $product->id )
                ->whereNull( 'product_variant_id' )
                ->orderBy( 'id' )
                ->get()
                ->map( static fn ( DigitalFile $file ): array => [
                    'id'                => (int) $file->id,
                    'label'             => (string) $file->label,
                    'version'           => (string) ( $file->version ?? '' ),
                    'is_streaming_only' => (bool) $file->is_streaming_only,
                    'source'            => null === $file->media_id ? 'path' : 'media',
                    'disk'              => (string) ( $file->disk ?? self::defaultDisk() ),
                    'path'              => (string) ( $file->path ?? '' ),
                    'media_id'          => $file->media_id,
                ] )
                ->all(),
            'download_limit'          => self::stringOrEmpty( $digital['download_limit'] ?? null ),
            'download_expiry_days'    => self::stringOrEmpty( $digital['download_expiry_days'] ?? null ),
            'licensing_enabled'       => true === ( $licensing['enabled'] ?? false ),
            'activations_limit'       => self::stringOrEmpty( $licensing['activations_limit'] ?? null ),
            'license_expires_in_days' => self::stringOrEmpty( $licensing['expires_in_days'] ?? null ),
        ];
    }

    /**
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $state    State.
     * @param  Product|null          $product  Product.
     *
     * @return array<string, mixed>
     */
    public static function rules( array $state, ?Product $product ): array
    {
        return [
            'files'                     => [ 'array', 'max:50' ],
            'files.*.label'             => [ 'required', 'string', 'max:255' ],
            'files.*.version'           => [ 'nullable', 'string', 'max:60' ],
            'files.*.is_streaming_only' => [ 'boolean' ],
            'files.*.source'            => [ 'required', Rule::in( [ 'path', 'media' ] ) ],
            'files.*.disk'              => [ 'nullable', 'required_if:files.*.source,path', Rule::in( self::allowedDisks() ) ],
            'files.*.path'              => [ 'nullable', 'required_if:files.*.source,path', 'string', 'max:1000', self::relativePath() ],
            'files.*.media_id'          => [ 'nullable', 'required_if:files.*.source,media', 'integer', 'min:1' ],
            'download_limit'            => [ 'nullable', 'integer', 'min:0', 'max:100000' ],
            'download_expiry_days'      => [ 'nullable', 'integer', 'min:0', 'max:36500' ],
            'licensing_enabled'         => [ 'boolean' ],
            'activations_limit'         => [ 'nullable', 'integer', 'min:1', 'max:100000' ],
            'license_expires_in_days'   => [ 'nullable', 'integer', 'min:1', 'max:36500' ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public static function validationAttributes(): array
    {
        return [
            'files.*.label'           => __( 'file label' ),
            'files.*.version'         => __( 'version' ),
            'files.*.disk'            => __( 'disk' ),
            'files.*.path'            => __( 'path' ),
            'files.*.media_id'        => __( 'media file' ),
            'download_limit'          => __( 'download limit' ),
            'download_expiry_days'    => __( 'download expiry' ),
            'activations_limit'       => __( 'activation limit' ),
            'license_expires_in_days' => __( 'license lifetime' ),
        ];
    }

    /**
     * @since 1.0.0
     *
     * @param  Product               $product  Saved product.
     * @param  array<string, mixed>  $state    Validated state.
     *
     * @return void
     */
    public static function save( Product $product, array $state ): void
    {
        $files = app( DigitalFileService::class );
        $keep  = [];

        foreach ( array_values( (array) ( $state['files'] ?? [] ) ) as $row ) {
            $media      = 'media' === ( $row['source'] ?? 'path' );
            $attributes = [
                'label'             => trim( sanitizeText( (string) $row['label'] ) ),
                'version'           => '' === trim( (string) ( $row['version'] ?? '' ) ) ? null : trim( sanitizeText( (string) $row['version'] ) ),
                'is_streaming_only' => (bool) ( $row['is_streaming_only'] ?? false ),
                'media_id'          => $media ? (int) $row['media_id'] : null,
                'disk'              => $media ? null : (string) $row['disk'],
                'path'              => $media ? null : ltrim( (string) $row['path'], '/' ),
            ];

            $existing = isset( $row['id'] )
                ? DigitalFile::query()->where( 'product_id', $product->id )->whereKey( (int) $row['id'] )->first()
                : null;

            $file   = null === $existing
                ? $files->create( $attributes + [ 'product_id' => $product->id ] )
                : $files->update( $existing, $attributes );
            $keep[] = (int) $file->id;
        }

        DigitalFile::query()
            ->where( 'product_id', $product->id )
            ->whereNull( 'product_variant_id' )
            ->whereNotIn( 'id', $keep )
            ->get()
            ->each( static fn ( DigitalFile $file ) => $file->delete() );

        app( ProductService::class )->update( $product, [ 'meta' => [
            'digital'   => array_filter( [
                'download_limit'       => self::intOrNull( $state['download_limit'] ?? null ),
                'download_expiry_days' => self::intOrNull( $state['download_expiry_days'] ?? null ),
            ], static fn ( ?int $value ): bool => null !== $value ),
            'licensing' => array_filter( [
                'enabled'           => (bool) ( $state['licensing_enabled'] ?? false ),
                'activations_limit' => self::intOrNull( $state['activations_limit'] ?? null ),
                'expires_in_days'   => self::intOrNull( $state['license_expires_in_days'] ?? null ),
            ], static fn ( mixed $value ): bool => null !== $value ),
        ] ] );
    }

    /**
     * Adds a file row.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addFile(): void
    {
        $this->assertWritable();

        $this->state['files'][] = [
            'id'                => null,
            'label'             => '',
            'version'           => '',
            'is_streaming_only' => false,
            'source'            => ProductMedia::libraryInstalled() ? 'media' : 'path',
            'disk'              => self::defaultDisk(),
            'path'              => '',
            'media_id'          => null,
        ];
    }

    /**
     * Removes a file row (deleted on save, with its download entitlements).
     *
     * @since 1.0.0
     *
     * @param  int  $index  Row index.
     *
     * @return void
     */
    public function removeFile( int $index ): void
    {
        $this->assertWritable();

        unset( $this->state['files'][ $index ] );
        $this->state['files'] = array_values( (array) ( $this->state['files'] ?? [] ) );
    }

    /**
     * Receives a media-library file for a row.
     *
     * @since 1.0.0
     *
     * @param  array<int, array<string, mixed>>  $media    Selected media.
     * @param  string                            $context  Modal context.
     *
     * @return void
     */
    #[On( 'media-selected' )]
    public function mediaSelected( array $media = [], string $context = '' ): void
    {
        if ( $this->readOnly || ! str_starts_with( $context, self::MEDIA_CONTEXT ) ) {
            return;
        }

        $index = (int) Str::after( $context, self::MEDIA_CONTEXT );
        $item  = $media[0] ?? null;

        if ( isset( $this->state['files'][ $index ] ) && is_array( $item ) && is_numeric( $item['id'] ?? null ) ) {
            $this->state['files'][ $index ]['source']   = 'media';
            $this->state['files'][ $index ]['media_id'] = (int) $item['id'];

            if ( '' === trim( (string) $this->state['files'][ $index ]['label'] ) && is_string( $item['title'] ?? null ) ) {
                $this->state['files'][ $index ]['label'] = $item['title'];
            }
        }
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        return view( 'ecommerce-admin::livewire.products.panels.digital', [
            'mediaLibrary'     => ProductMedia::libraryInstalled(),
            'diskOptions'      => array_map( static fn ( string $disk ): array => [ 'id' => $disk, 'name' => $disk ], self::allowedDisks() ),
            'defaultLimit'     => (int) config( 'artisanpack.ecommerce.digital.download_limit', 5 ),
            'defaultExpiry'    => (int) config( 'artisanpack.ecommerce.digital.download_expiry_days', 30 ),
        ] );
    }

    /**
     * The engine's allowed digital disks.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected static function allowedDisks(): array
    {
        return DigitalDisks::allowed();
    }

    /**
     * The default digital disk.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected static function defaultDisk(): string
    {
        return DigitalDisks::default();
    }

    /**
     * Rule: a path inside the disk (no `..`, not absolute), as the engine
     * requires.
     *
     * @since 1.0.0
     *
     * @return Closure
     */
    protected static function relativePath(): Closure
    {
        return DigitalDisks::relativePath();
    }

    /**
     * A stored number as form text.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  Stored value.
     *
     * @return string
     */
    protected static function stringOrEmpty( mixed $value ): string
    {
        return is_numeric( $value ) ? (string) (int) $value : '';
    }

    /**
     * Form text as a number, or null.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  Input.
     *
     * @return int|null
     */
    protected static function intOrNull( mixed $value ): ?int
    {
        return is_numeric( $value ) ? (int) $value : null;
    }
}
