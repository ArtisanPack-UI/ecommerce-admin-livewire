<?php

/**
 * CSV reading and writing.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Support;

use Generator;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Reads uploaded CSV files and builds CSV downloads for the admin's
 * imports and exports.
 *
 * - Reading strips a UTF-8 byte-order mark, detects `,` / `;` / tab
 *   delimiters from the header line, trims headers, skips rows whose cells
 *   are all blank, and stops after a row limit.
 * - Writing prefixes text a spreadsheet would run as a formula (`=`, `+`,
 *   `-`, `@`, tab, carriage return) with an apostrophe, unless it is a
 *   plain number, and starts with a byte-order mark so Excel reads UTF-8.
 *   {@see self::uncell()} removes that apostrophe again on import.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
final class Csv
{
    /**
     * MIME types accepted for CSV uploads.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const MIMES = [ 'csv', 'txt' ];

    /**
     * The headers of a CSV file.
     *
     * @since 1.0.0
     *
     * @param  string  $path  File path.
     *
     * @return array<int, string>
     */
    public static function headers( string $path ): array
    {
        return self::read( $path, 0 )['headers'];
    }

    /**
     * Reads a CSV file into header-keyed rows.
     *
     * Each row also carries `__line`, its line number in the file (the
     * header is line 1). Columns beyond the headers are dropped; missing
     * ones are empty strings.
     *
     * @since 1.0.0
     *
     * @param  string  $path     File path.
     * @param  int     $limit    Most data rows to return (0 for headers only).
     * @param  int     $offset   Data rows to skip first.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<string, int|string>>, total: int}
     */
    public static function read( string $path, int $limit = PHP_INT_MAX, int $offset = 0 ): array
    {
        $handle = fopen( $path, 'r' );

        if ( false === $handle ) {
            return [ 'headers' => [], 'rows' => [], 'total' => 0 ];
        }

        $first     = (string) preg_replace( '/^\xEF\xBB\xBF/', '', rtrim( (string) fgets( $handle ), "\r\n" ) );
        $delimiter = self::delimiter( $first );
        $headers   = array_map( static fn ( mixed $header ): string => trim( (string) $header ), str_getcsv( $first, $delimiter, '"', '' ) );
        $rows      = [];
        $total     = 0;
        $line      = 1;

        while ( false !== ( $values = fgetcsv( $handle, null, $delimiter, '"', '' ) ) ) {
            ++$line;

            if ( [ null ] === $values || [] === array_filter( $values, static fn ( mixed $value ): bool => '' !== trim( (string) $value ) ) ) {
                continue;
            }

            ++$total;

            if ( $total <= $offset || count( $rows ) >= $limit ) {
                continue;
            }

            $row = [ '__line' => $line ];

            foreach ( $headers as $index => $header ) {
                if ( '' !== $header && '__line' !== $header ) {
                    $row[ $header ] = trim( (string) ( $values[ $index ] ?? '' ) );
                }
            }

            $rows[] = $row;
        }

        fclose( $handle );

        return [ 'headers' => array_values( array_filter( $headers, static fn ( string $header ): bool => '' !== $header && '__line' !== $header ) ), 'rows' => $rows, 'total' => $total ];
    }

    /**
     * Streams a CSV file's data rows from an offset, one at a time, in the
     * same shape as {@see self::read()} (header-keyed, with `__line`).
     *
     * @since 1.0.0
     *
     * @param  string  $path    File path.
     * @param  int     $offset  Data rows to skip first.
     *
     * @return Generator<int, array<string, int|string>>
     */
    public static function rows( string $path, int $offset = 0 ): Generator
    {
        $handle = fopen( $path, 'r' );

        if ( false === $handle ) {
            return;
        }

        try {
            $first     = (string) preg_replace( '/^\xEF\xBB\xBF/', '', rtrim( (string) fgets( $handle ), "\r\n" ) );
            $delimiter = self::delimiter( $first );
            $headers   = array_map( static fn ( mixed $header ): string => trim( (string) $header ), str_getcsv( $first, $delimiter, '"', '' ) );
            $seen      = 0;
            $line      = 1;

            while ( false !== ( $values = fgetcsv( $handle, null, $delimiter, '"', '' ) ) ) {
                ++$line;

                if ( [ null ] === $values || [] === array_filter( $values, static fn ( mixed $value ): bool => '' !== trim( (string) $value ) ) ) {
                    continue;
                }

                if ( ++$seen <= $offset ) {
                    continue;
                }

                $row = [ '__line' => $line ];

                foreach ( $headers as $index => $header ) {
                    if ( '' !== $header && '__line' !== $header ) {
                        $row[ $header ] = trim( (string) ( $values[ $index ] ?? '' ) );
                    }
                }

                yield $row;
            }
        } finally {
            fclose( $handle );
        }
    }

    /**
     * Undoes {@see self::cell()}'s formula guard: drops the apostrophe in
     * front of text that starts with a formula character, so an exported
     * file imports back unchanged.
     *
     * @since 1.0.0
     *
     * @param  string  $value  Cell.
     *
     * @return string
     */
    public static function uncell( string $value ): string
    {
        return 1 === preg_match( "/^'[=+\-@\t\r]/", $value ) ? substr( $value, 1 ) : $value;
    }

    /**
     * Calls `$read` with a local path to an upload. Livewire keeps uploads on
     * its temporary disk, which may be S3; those are copied to a local
     * temporary file first.
     *
     * @since 1.0.0
     *
     * @template TResult
     *
     * @param  TemporaryUploadedFile      $file  Upload.
     * @param  callable(string): TResult  $read  Reader.
     *
     * @return TResult
     */
    public static function withLocalUpload( TemporaryUploadedFile $file, callable $read ): mixed
    {
        $path = $file->getRealPath();

        if ( is_string( $path ) && '' !== $path && is_file( $path ) ) {
            return $read( $path );
        }

        $temporary = (string) tempnam( sys_get_temp_dir(), 'ec-upload-' );

        try {
            $stream = $file->readStream();
            file_put_contents( $temporary, $stream );

            if ( is_resource( $stream ) ) {
                fclose( $stream );
            }

            return $read( $temporary );
        } finally {
            @unlink( $temporary );
        }
    }

    /**
     * Builds CSV text from headers and rows.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>                     $headers  Header labels.
     * @param  iterable<int, array<int, mixed>>       $rows     Rows, values in header order.
     *
     * @return string
     */
    public static function build( array $headers, iterable $rows ): string
    {
        $handle = fopen( 'php://temp', 'r+' );

        fwrite( $handle, "\xEF\xBB\xBF" );
        fputcsv( $handle, array_map( self::cell( ... ), $headers ), ',', '"', '' );

        foreach ( $rows as $row ) {
            fputcsv( $handle, array_map( self::cell( ... ), array_values( $row ) ), ',', '"', '' );
        }

        rewind( $handle );
        $csv = (string) stream_get_contents( $handle );
        fclose( $handle );

        return $csv;
    }

    /**
     * Makes a value safe for a CSV cell.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  The value.
     *
     * @return string
     */
    public static function cell( mixed $value ): string
    {
        if ( is_bool( $value ) ) {
            return $value ? '1' : '0';
        }

        $text = is_scalar( $value ) ? (string) $value : '';

        if ( '' !== $text && ! is_numeric( $text ) && in_array( $text[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
            return "'" . $text;
        }

        return $text;
    }

    /**
     * The delimiter a header line uses: the most frequent of `,`, `;`, tab.
     *
     * @since 1.0.0
     *
     * @param  string  $line  Header line.
     *
     * @return string
     */
    private static function delimiter( string $line ): string
    {
        $counts = [ ',' => substr_count( $line, ',' ), ';' => substr_count( $line, ';' ), "\t" => substr_count( $line, "\t" ) ];
        arsort( $counts );

        return 0 === reset( $counts ) ? ',' : (string) array_key_first( $counts );
    }
}
