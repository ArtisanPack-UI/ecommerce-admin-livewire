<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceAdminLivewire\Jobs\RunProductImport;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductImports;
use Illuminate\Support\Facades\Storage;

beforeEach( function (): void {
    config()->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    config()->set( 'artisanpack.ecommerce.features.scout', false );
    config()->set( 'artisanpack.ecommerce-admin-livewire.imports.disk', 'imports' );
    grantAbilities( [ 'product.viewAny', 'product.view', 'product.create', 'product.update' ] );

    $this->user = makeUser();
    $this->actingAs( $this->user );
    config()->set( 'auth.providers.users.model', Tests\Fixtures\User::class );

    // A fake disk that counts state.json writes.
    $this->writes = 0;
    $disk         = Storage::fake( 'imports' );
    $counting     = Mockery::mock( $disk );
    $writes       = &$this->writes;

    $counting->shouldReceive( 'put' )->andReturnUsing( static function ( string $path, mixed $contents, mixed $options = [] ) use ( $disk, &$writes ): bool {
        if ( str_ends_with( $path, 'state.json' ) ) {
            ++$writes;
        }

        return $disk->put( $path, $contents, $options );
    } );

    Storage::set( 'imports', $counting );
} );

/**
 * Starts an import of `$csv`, mapped column by column, ready to run.
 *
 * @return array<string, mixed>
 */
function queuedImport( string $csv, int $total, int|string $userId ): array
{
    $path = tempnam( sys_get_temp_dir(), 'import-test-' );
    file_put_contents( $path, $csv );

    $headers = str_getcsv( strtok( $csv, "\n" ) );
    $state   = ProductImports::create( $userId, $path, 'products.csv', $headers, $total );

    $state['mapping'] = array_combine( $headers, $headers );
    $state['status']  = 'queued';

    @unlink( $path );

    return ProductImports::save( $state );
}

it( 'keeps at most 500 row errors and counts the rest', function (): void {
    $csv   = "name,status\n" . implode( "\n", array_map( static fn ( int $n ): string => "Mug {$n},nope", range( 1, 1000 ) ) ) . "\n";
    $state = queuedImport( $csv, 1000, $this->user->id );

    ( new RunProductImport( $state['id'] ) )->handle();

    $state = ProductImports::find( $state['id'] );

    expect( $state['status'] )->toBe( 'completed' )
        ->and( $state['counts']['failed'] )->toBe( 1000 )
        ->and( $state['errors'] )->toHaveCount( RunProductImport::MAX_ERRORS )
        ->and( $state['errors_truncated'] )->toBe( 500 )
        ->and( ProductImports::errorsCsv( $state ) )->toContain( '500 more rows failed; only the first 500 problems are listed.' );
} );

it( 'saves its progress every few rows, not after each one', function (): void {
    $rows  = 100;
    $csv   = "name,sku\n" . implode( "\n", array_map( static fn ( int $n ): string => "Mug {$n},MUG-{$n}", range( 1, $rows ) ) ) . "\n";
    $state = queuedImport( $csv, $rows, $this->user->id );

    $this->writes = 0;

    ( new RunProductImport( $state['id'] ) )->handle();

    expect( Product::query()->count() )->toBe( $rows )
        ->and( $this->writes )->toBeGreaterThan( 0 )
        ->and( $this->writes )->toBeLessThanOrEqual( (int) ceil( $rows / RunProductImport::SAVE_EVERY_ROWS ) + 3 );
} );

it( 'creates no duplicates when a resumed import replays rows', function (): void {
    $csv   = "name,sku\n" . implode( "\n", array_map( static fn ( int $n ): string => "Mug {$n},MUG-{$n}", range( 1, 30 ) ) ) . "\n";
    $state = queuedImport( $csv, 30, $this->user->id );

    ( new RunProductImport( $state['id'] ) )->handle();

    // Pretend the worker died with its last 10 rows unsaved.
    $state              = ProductImports::find( $state['id'] );
    $state['status']    = 'queued';
    $state['processed'] = 20;
    ProductImports::disk()->put( 'ecommerce-admin/imports/' . $state['id'] . '/source.csv', $csv );
    ProductImports::save( $state );

    ( new RunProductImport( $state['id'] ) )->handle();

    expect( Product::query()->count() )->toBe( 30 )
        ->and( ProductImports::find( $state['id'] )['status'] )->toBe( 'completed' );
} );

it( 'refuses a new product without a SKU or a slug, in the dry run and the apply', function (): void {
    $context = [];

    expect( ArtisanPackUI\EcommerceAdminLivewire\Support\ProductCsv::check( [ 'name' => 'Mug' ], $context ) )
        ->toMatchArray( [ 'action' => 'error', 'error' => 'A new product needs a SKU or a slug.' ] )
        ->and( ArtisanPackUI\EcommerceAdminLivewire\Support\ProductCsv::check( [ 'name' => 'Mug', 'slug' => 'mug' ], $context )['action'] )->toBe( 'create' );
} );

it( 'prunes old finished, failed, and abandoned imports, keeping active ones', function (): void {
    $make = function ( string $status ): string {
        $state           = queuedImport( "name,sku\nMug,MUG-1\n", 1, $this->user->id );
        $state['status'] = $status;

        return ProductImports::save( $state )['id'];
    };

    Illuminate\Support\Carbon::setTestNow( now()->subDays( ProductImports::KEEP_DAYS + 1 ) );

    $old = array_map( $make, [ 'completed', 'failed', 'mapping', 'queued', 'running' ] );

    Illuminate\Support\Carbon::setTestNow();

    $recent = $make( 'failed' );

    $this->artisan( 'ecommerce-admin:prune-imports' )
        ->expectsOutputToContain( 'Deleted 3 old imports.' )
        ->assertSuccessful();

    expect( array_map( static fn ( string $id ): bool => null !== ProductImports::find( $id ), $old ) )->toBe( [ false, false, false, true, true ] )
        ->and( ProductImports::find( $recent ) )->not->toBeNull()
        ->and( ProductImports::disk()->exists( 'ecommerce-admin/imports/' . $old[2] . '/source.csv' ) )->toBeFalse();
} );
