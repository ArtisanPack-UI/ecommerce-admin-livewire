<?php

/**
 * Product image helpers.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\DigitalFile;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductImage;
use ArtisanPackUI\MediaLibrary\Livewire\Components\MediaModal;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Throwable;

/**
 * Resolves product images whether or not `artisanpack-ui/media-library` is
 * installed (spec §4.2). With the library, images are media ids and the
 * pickers open its modal; without it, images are plain URLs.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class ProductMedia
{
    /**
     * The media-library modal's Livewire name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const MODAL = 'media::media-modal';

    /**
     * Override for tests: true/false forces the answer, null detects.
     *
     * @since 1.0.0
     *
     * @var bool|null
     */
    private static ?bool $fake = null;

    /**
     * Stand-in modal component for tests.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    private static ?string $fakeModal = null;

    /**
     * Whether the media library is installed.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function libraryInstalled(): bool
    {
        return self::$fake ?? ( class_exists( MediaModal::class ) && function_exists( 'apGetMediaUrl' ) );
    }

    /**
     * Forces {@see self::libraryInstalled()} (null restores detection).
     *
     * @since 1.0.0
     *
     * @param  bool|null    $installed  Forced answer.
     * @param  string|null  $modal      Livewire component standing in for the modal.
     *
     * @return void
     */
    public static function fake( ?bool $installed, ?string $modal = null ): void
    {
        self::$fake      = $installed;
        self::$fakeModal = $modal;
    }

    /**
     * The media-library modal to mount. The class is used when it exists,
     * which sidesteps Livewire 4 resolving `media::` names through a
     * namespace rather than an alias.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function modal(): string
    {
        return self::$fakeModal ?? ( class_exists( MediaModal::class ) ? MediaModal::class : self::MODAL );
    }

    /**
     * URL of a media-library item, or null.
     *
     * @since 1.0.0
     *
     * @param  int|null  $mediaId  Media id.
     * @param  string    $size     Image size name.
     *
     * @return string|null
     */
    public static function mediaUrl( ?int $mediaId, string $size = 'thumbnail' ): ?string
    {
        if ( null === $mediaId || ! function_exists( 'apGetMediaUrl' ) ) {
            return null;
        }

        try {
            $url = apGetMediaUrl( $mediaId, $size );
        } catch ( Throwable ) {
            return null;
        }

        return is_string( $url ) && '' !== $url ? $url : null;
    }

    /**
     * URL of a gallery image.
     *
     * @since 1.0.0
     *
     * @param  ProductImage  $image  Image.
     * @param  string        $size   Image size name.
     *
     * @return string|null
     */
    public static function imageUrl( ProductImage $image, string $size = 'thumbnail' ): ?string
    {
        return self::mediaUrl( $image->media_id, $size ) ?? self::safeUrl( $image->image_url );
    }

    /**
     * The product's featured image (`url`, `alt`): its media item, its
     * `meta.featured_image_url`, or else the first gallery image.
     *
     * @since 1.0.0
     *
     * @param  Product  $product  Product (eager-load `images` to avoid a query).
     * @param  string   $size     Image size name.
     *
     * @return array{url: string, alt: string}|null
     */
    public static function featured( Product $product, string $size = 'thumbnail' ): ?array
    {
        $url = self::mediaUrl( $product->featured_image_media_id, $size )
            ?? self::safeUrl( $product->meta['featured_image_url'] ?? null );

        if ( null !== $url ) {
            return [ 'url' => $url, 'alt' => (string) $product->name ];
        }

        $first = $product->images->first();

        if ( null === $first ) {
            return null;
        }

        $url = self::imageUrl( $first, $size );

        return null === $url ? null : [ 'url' => $url, 'alt' => (string) ( $first->alt_text ?? $product->name ) ];
    }

    /**
     * {@see self::featured()} for a page of products at once, keyed by
     * product id. The gallery images of products without a featured image
     * load in one query, and media-library items in one query where the
     * library's model can build its own URLs, instead of a lookup per row.
     *
     * @since 2.0.0
     *
     * @param  iterable<int, Product>  $products  Products.
     * @param  string                  $size      Image size.
     *
     * @return array<int, array{url: string, alt: string}|null>
     */
    public static function featuredMany( iterable $products, string $size = 'thumbnail' ): array
    {
        $products = collect( $products )->filter( static fn ( mixed $product ): bool => $product instanceof Product )->values();

        if ( $products->isEmpty() ) {
            return [];
        }

        // Only products without a featured image fall back to their gallery.
        $needImages = $products->filter( static fn ( Product $product ): bool => null === $product->featured_image_media_id && null === self::safeUrl( $product->meta['featured_image_url'] ?? null ) );

        if ( $needImages->isNotEmpty() ) {
            ( new EloquentCollection( $needImages->all() ) )->loadMissing( 'images' );
        }

        $mediaIds = [];

        foreach ( $products as $product ) {
            $mediaIds[] = $product->featured_image_media_id;
            $mediaIds[] = $product->relationLoaded( 'images' ) ? $product->images->first()?->media_id : null;
        }

        $urls  = self::mediaUrls( array_values( array_unique( array_map( 'intval', array_filter( $mediaIds ) ) ) ), $size );
        $thumb = [];

        foreach ( $products as $product ) {
            $url = ( null === $product->featured_image_media_id ? null : ( $urls[ (int) $product->featured_image_media_id ] ?? null ) )
                ?? self::safeUrl( $product->meta['featured_image_url'] ?? null );

            if ( null !== $url ) {
                $thumb[ (int) $product->id ] = [ 'url' => $url, 'alt' => (string) $product->name ];

                continue;
            }

            $first = $product->images->first();
            $url   = null === $first ? null : ( ( null === $first->media_id ? null : ( $urls[ (int) $first->media_id ] ?? null ) ) ?? self::safeUrl( $first->image_url ) );

            $thumb[ (int) $product->id ] = null === $url ? null : [ 'url' => $url, 'alt' => (string) ( $first->alt_text ?? $product->name ) ];
        }

        return $thumb;
    }

    /**
     * Media-library URLs for several items, keyed by id. With the library's
     * model (and its `imageUrl()`), the items load in one query; otherwise
     * each goes through {@see self::mediaUrl()}.
     *
     * @since 2.0.0
     *
     * @param  array<int, int>  $ids   Media ids.
     * @param  string           $size  Image size.
     *
     * @return array<int, string>
     */
    public static function mediaUrls( array $ids, string $size = 'thumbnail' ): array
    {
        if ( [] === $ids || ! function_exists( 'apGetMediaUrl' ) ) {
            return [];
        }

        $model = DigitalFile::MEDIA_MODEL;
        $urls  = [];

        if ( class_exists( $model ) && method_exists( $model, 'imageUrl' ) ) {
            foreach ( $model::query()->whereKey( $ids )->get() as $media ) {
                try {
                    $url = method_exists( $media, 'isImage' ) && ! $media->isImage() && method_exists( $media, 'url' )
                        ? $media->url()
                        : $media->imageUrl( $size );
                } catch ( Throwable ) {
                    $url = null;
                }

                if ( is_string( $url ) && '' !== $url ) {
                    $urls[ (int) $media->getKey() ] = $url;
                }
            }

            return $urls;
        }

        foreach ( $ids as $id ) {
            $url = self::mediaUrl( $id, $size );

            if ( null !== $url ) {
                $urls[ $id ] = $url;
            }
        }

        return $urls;
    }

    /**
     * An http(s) URL, or null.
     *
     * @since 1.0.0
     *
     * @param  mixed  $url  Candidate.
     *
     * @return string|null
     */
    public static function safeUrl( mixed $url ): ?string
    {
        if ( ! is_string( $url ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return null;
        }

        return in_array( strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ), [ 'http', 'https' ], true ) ? $url : null;
    }
}
