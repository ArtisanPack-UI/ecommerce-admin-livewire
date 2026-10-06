<?php

/**
 * Product import storage.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use ArtisanPackUI\EcommerceAdminLivewire\Jobs\RunProductImport;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Keeps product imports on the `imports.disk` disk, so the package needs no
 * table (spec §13). Each import is a folder, `ecommerce-admin/imports/{id}/`,
 * holding the uploaded `source.csv` and a `state.json`:
 *
 * - `status` — `mapping` → `checked` → `queued` → `running` → `completed`,
 *   or `failed` (resumable);
 * - `mapping` — CSV header => column;
 * - `total`, `processed` — rows in the file, and rows applied so far (the
 *   resume point);
 * - `counts` — created, updated, and failed rows;
 * - `errors` — `{ line, label, message }` per failed row;
 * - `report` — the dry-run result.
 *
 * The source file is deleted when the import completes or is discarded,
 * and completed imports are pruned after {@see self::KEEP_DAYS} days.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class ProductImports
{
    /**
     * Folder imports are kept in.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const DIRECTORY = 'ecommerce-admin/imports';

    /**
     * Statuses during which rows are being written.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const ACTIVE = [ 'queued', 'running' ];

    /**
     * Days a completed import's report is kept before it is pruned.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const KEEP_DAYS = 7;

    /**
     * The cache lock that keeps one worker per import.
     *
     * @since 1.0.0
     *
     * @param  string  $id  Import id.
     *
     * @return string
     */
    public static function lockKey( string $id ): string
    {
        return 'ecommerce-admin-import:' . $id;
    }

    /**
     * Starts an import from an uploaded file.
     *
     * @since 1.0.0
     *
     * @param  int|string  $userId    Owner.
     * @param  string      $path      Local path of the uploaded file.
     * @param  string      $fileName  The file's original name.
     * @param  array<int, string>  $headers  The file's headers.
     * @param  int         $total     Data rows in the file.
     *
     * @return array<string, mixed> The new state.
     */
    public static function create( int|string $userId, string $path, string $fileName, array $headers, int $total ): array
    {
        $id     = (string) Str::uuid();
        $stream = fopen( $path, 'r' );

        self::disk()->writeStream( self::path( $id, 'source.csv' ), $stream );

        if ( is_resource( $stream ) ) {
            fclose( $stream );
        }

        return self::save( [
            'id'         => $id,
            'user_id'    => (string) $userId,
            'file_name'  => mb_substr( basename( $fileName ), 0, 200 ),
            'status'     => 'mapping',
            'headers'    => $headers,
            'mapping'    => ProductCsv::guessMapping( $headers ),
            'total'      => $total,
            'processed'  => 0,
            'counts'     => [ 'created' => 0, 'updated' => 0, 'failed' => 0 ],
            'errors'     => [],
            'report'     => null,
            'message'    => null,
            'created_at' => Carbon::now()->toIso8601String(),
        ] );
    }

    /**
     * An import's state, or null when it does not exist.
     *
     * @since 1.0.0
     *
     * @param  string  $id  Import id.
     *
     * @return array<string, mixed>|null
     */
    public static function find( string $id ): ?array
    {
        if ( ! Str::isUuid( $id ) ) {
            return null;
        }

        try {
            $json = self::disk()->get( self::path( $id, 'state.json' ) );
        } catch ( Throwable ) {
            return null;
        }

        $state = is_string( $json ) ? json_decode( $json, true ) : null;

        return is_array( $state ) && ( $state['id'] ?? null ) === $id ? $state : null;
    }

    /**
     * An import, when it belongs to the user.
     *
     * @since 1.0.0
     *
     * @param  string      $id      Import id.
     * @param  int|string  $userId  User.
     *
     * @return array<string, mixed>|null
     */
    public static function findFor( string $id, int|string|null $userId ): ?array
    {
        $state = self::find( $id );

        return null !== $state && null !== $userId && (string) $userId === (string) $state['user_id'] ? $state : null;
    }

    /**
     * The user's most recent import that has not completed.
     *
     * @since 1.0.0
     *
     * @param  int|string|null  $userId  User.
     *
     * @return array<string, mixed>|null
     */
    public static function latestUnfinishedFor( int|string|null $userId ): ?array
    {
        if ( null === $userId ) {
            return null;
        }

        $latest = null;

        foreach ( self::disk()->directories( self::DIRECTORY ) as $directory ) {
            $state = self::find( basename( $directory ) );

            if ( null === $state ) {
                continue;
            }

            // Tidies up as it goes, for hosts that don't schedule
            // `ecommerce-admin:prune-imports`.
            if ( self::isStale( $state ) ) {
                self::delete( $state );

                continue;
            }

            if ( 'completed' === $state['status'] || (string) $userId !== (string) $state['user_id'] ) {
                continue;
            }

            if ( null === $latest || (string) $state['updated_at'] > (string) $latest['updated_at'] ) {
                $latest = $state;
            }
        }

        return $latest;
    }

    /**
     * Writes an import's state.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $state  State.
     *
     * @return array<string, mixed>
     */
    /**
     * Deletes every import that is not queued or running and was last
     * touched more than {@see self::KEEP_DAYS} days ago: completed, failed,
     * and abandoned ones (still at the mapping or dry-run step), with their
     * uploaded files.
     *
     * @since 1.0.0
     *
     * @return int How many were deleted.
     */
    public static function prune(): int
    {
        $deleted = 0;

        foreach ( self::disk()->directories( self::DIRECTORY ) as $directory ) {
            $state = self::find( basename( $directory ) );

            if ( null !== $state && self::isStale( $state ) ) {
                self::delete( $state );
                ++$deleted;
            }
        }

        return $deleted;
    }

    /**
     * Whether an import is inactive and older than {@see self::KEEP_DAYS} days.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $state  Import state.
     *
     * @return bool
     */
    public static function isStale( array $state ): bool
    {
        return ! in_array( $state['status'] ?? null, self::ACTIVE, true )
            && (string) ( $state['updated_at'] ?? '' ) < Carbon::now()->subDays( self::KEEP_DAYS )->toIso8601String();
    }

    public static function save( array $state ): array
    {
        $state['updated_at'] = Carbon::now()->toIso8601String();

        self::disk()->put( self::path( (string) $state['id'], 'state.json' ), (string) json_encode( $state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

        return $state;
    }

    /**
     * Calls `$read` with a local path to the import's source file.
     *
     * Disks without local paths (S3 and the like) are copied to a
     * temporary file first.
     *
     * @since 1.0.0
     *
     * @template TResult
     *
     * @param  array<string, mixed>       $state  State.
     * @param  callable(string): TResult  $read   Reader.
     *
     * @return TResult
     */
    public static function withSource( array $state, callable $read ): mixed
    {
        $disk = self::disk();
        $path = self::path( (string) $state['id'], 'source.csv' );

        try {
            $local = $disk->path( $path );

            if ( is_file( $local ) ) {
                return $read( $local );
            }
        } catch ( Throwable ) {
            // Fall through to a temporary copy.
        }

        $temporary = (string) tempnam( sys_get_temp_dir(), 'ec-import-' );

        try {
            file_put_contents( $temporary, $disk->readStream( $path ) );

            return $read( $temporary );
        } finally {
            @unlink( $temporary );
        }
    }

    /**
     * Whether the import's source file is still there.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $state  State.
     *
     * @return bool
     */
    public static function hasSource( array $state ): bool
    {
        return self::disk()->exists( self::path( (string) $state['id'], 'source.csv' ) );
    }

    /**
     * Deletes the uploaded file, keeping the state for the report.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $state  State.
     *
     * @return void
     */
    public static function deleteSource( array $state ): void
    {
        self::disk()->delete( self::path( (string) $state['id'], 'source.csv' ) );
    }

    /**
     * Deletes an import entirely.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $state  State.
     *
     * @return void
     */
    public static function delete( array $state ): void
    {
        self::disk()->deleteDirectory( self::DIRECTORY . '/' . $state['id'] );
    }

    /**
     * Queues the rows from the resume point on.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $state  State.
     *
     * @return array<string, mixed>
     */
    public static function dispatch( array $state ): array
    {
        // The dry-run report is not needed once rows are being written, and
        // the state is saved every few rows.
        $state['status']  = 'queued';
        $state['message'] = null;
        $state['report']  = null;
        $state            = self::save( $state );

        $job   = new RunProductImport( (string) $state['id'] );
        $queue = config( 'artisanpack.ecommerce-admin-livewire.imports.queue' );

        if ( is_string( $queue ) && '' !== $queue ) {
            $job->onQueue( $queue );
        }

        dispatch( $job );

        return self::find( (string) $state['id'] ) ?? $state;
    }

    /**
     * The failed rows as CSV.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $state  State.
     *
     * @return string
     */
    public static function errorsCsv( array $state ): string
    {
        $rows = array_map(
            static fn ( array $error ): array => [ $error['line'] ?? '', $error['label'] ?? '', $error['message'] ?? '' ],
            (array) ( $state['errors'] ?? [] ),
        );

        $truncated = (int) ( $state['errors_truncated'] ?? 0 );

        if ( $truncated > 0 ) {
            $rows[] = [ '', '', trans_choice( ':count more row failed; only the first :max problems are listed.|:count more rows failed; only the first :max problems are listed.', $truncated, [ 'count' => $truncated, 'max' => RunProductImport::MAX_ERRORS ] ) ];
        }

        return Csv::build( [ __( 'Line' ), __( 'Row' ), __( 'Problem' ) ], $rows );
    }

    /**
     * Maps a CSV row through the import's mapping.
     *
     * @since 1.0.0
     *
     * @param  array<string, int|string>  $row      Row keyed by header.
     * @param  array<string, string>      $mapping  Header => column.
     *
     * @return array<string, string>
     */
    public static function mapRow( array $row, array $mapping ): array
    {
        $mapped = [];

        foreach ( $mapping as $header => $column ) {
            if ( '' !== $column && array_key_exists( $header, $row ) ) {
                $mapped[ $column ] = Csv::uncell( (string) $row[ $header ] );
            }
        }

        return $mapped;
    }

    /**
     * The imports disk.
     *
     * @since 1.0.0
     *
     * @return Filesystem
     */
    public static function disk(): Filesystem
    {
        return Storage::disk( (string) config( 'artisanpack.ecommerce-admin-livewire.imports.disk', 'local' ) );
    }

    /**
     * A file inside an import's folder.
     *
     * @since 1.0.0
     *
     * @param  string  $id    Import id.
     * @param  string  $file  File name.
     *
     * @return string
     */
    private static function path( string $id, string $file ): string
    {
        return self::DIRECTORY . '/' . $id . '/' . $file;
    }
}
