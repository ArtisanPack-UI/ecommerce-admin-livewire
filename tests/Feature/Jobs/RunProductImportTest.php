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
    grantAbilities( [ 'product.viewAny', 'product.view', 'product.create', 'product.update', 'inventory.adjust' ] );

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
    // A queued or running import untouched for days has no live worker
    // (it would have saved its progress), so it is pruned too.
    $make = function ( string $status ): string {
        $state           = queuedImport( "name,sku\nMug,MUG-1\n", 1, $this->user->id );
        $state['status'] = $status;

        return ProductImports::save( $state )['id'];
    };

    Illuminate\Support\Carbon::setTestNow( now()->subDays( ProductImports::KEEP_DAYS + 1 ) );

    $old = array_map( $make, [ 'completed', 'failed', 'mapping', 'queued', 'running' ] );

    Illuminate\Support\Carbon::setTestNow();

    $recent  = $make( 'failed' );
    $running = $make( 'running' );

    $this->artisan( 'ecommerce-admin:prune-imports' )
        ->expectsOutputToContain( 'Deleted 5 old imports.' )
        ->assertSuccessful();

    expect( array_map( static fn ( string $id ): bool => null !== ProductImports::find( $id ), $old ) )->toBe( [ false, false, false, false, false ] )
        ->and( ProductImports::find( $recent ) )->not->toBeNull()
        ->and( ProductImports::find( $running ) )->not->toBeNull()
        ->and( ProductImports::disk()->exists( 'ecommerce-admin/imports/' . $old[2] . '/source.csv' ) )->toBeFalse();
} );

it( 'marks an abandoned import failed even when a dead worker still holds the lock', function (): void {
    $state           = queuedImport( "name,sku\nMug,MUG-1\n", 1, $this->user->id );
    $state['status'] = 'running';
    ProductImports::save( $state );

    // The killed worker's lock is still held.
    Illuminate\Support\Facades\Cache::lock( ProductImports::lockKey( $state['id'] ), 10000 )->get();

    $job = new RunProductImport( $state['id'] );

    // Still saving progress: another worker may own it, so leave it alone.
    $job->failed( new RuntimeException( 'Timed out' ) );

    expect( ProductImports::find( $state['id'] )['status'] )->toBe( 'running' );

    $this->travel( RunProductImport::TIMEOUT + ProductImports::ABANDONED_GRACE_SECONDS + 5 )->seconds();

    $job->failed( new RuntimeException( 'Timed out' ) );

    expect( ProductImports::find( $state['id'] )['status'] )->toBe( 'failed' );
} );

it( 'records the import\'s owner as the actor of what it writes', function (): void {
    $state = queuedImport( "name,sku\nMug,MUG-1\n", 1, $this->user->id );

    // A queue worker has no signed-in user.
    app( 'auth' )->forgetGuards();

    ( new RunProductImport( $state['id'] ) )->handle();

    $product = Product::query()->where( 'sku', 'MUG-1' )->firstOrFail();
    $entries = ArtisanPackUI\Ecommerce\Models\ActivityLogEntry::query()
        ->where( 'subject_type', $product->getMorphClass() )
        ->where( 'subject_id', $product->id )
        ->get();

    expect( $entries )->not->toBeEmpty()
        ->and( $entries->pluck( 'actor_user_id' )->unique()->all() )->toBe( [ (int) $this->user->id ] )
        ->and( auth()->hasUser() )->toBeFalse();
} );

it( 'imports a Windows-1252 file with accented headers and cells', function (): void {
    $csv   = mb_convert_encoding( "name,sku,Catégorie\nCafé crème,CAFE-1,x\n", 'Windows-1252', 'UTF-8' );
    $state = queuedImport( $csv, 1, $this->user->id );

    $state['mapping'] = [ 'name' => 'name', 'sku' => 'sku', 'Catégorie' => '' ];
    ProductImports::save( $state );

    ( new RunProductImport( $state['id'] ) )->handle();

    expect( ProductImports::find( $state['id'] )['status'] )->toBe( 'completed' )
        ->and( Product::query()->where( 'sku', 'CAFE-1' )->value( 'name' ) )->toBe( 'Café crème' );
} );

it( 'never blanks the state file when it holds invalid UTF-8', function (): void {
    $state              = queuedImport( "name,sku\nMug,MUG-1\n", 1, $this->user->id );
    $state['file_name'] = "Caf\xE9.csv";

    ProductImports::save( $state );

    $saved = ProductImports::find( $state['id'] );

    expect( $saved )->not->toBeNull()
        ->and( $saved['file_name'] )->toBe( "Caf\u{FFFD}.csv" );
} );

it( 'skips stock columns, with a warning, for a user who may not adjust stock', function (): void {
    Illuminate\Support\Facades\Gate::define( 'ecommerce.inventory.adjust', static fn (): bool => false );

    $state = queuedImport( "name,sku,quantity_on_hand\nMug,MUG-1,25\n", 1, $this->user->id );

    ( new RunProductImport( $state['id'] ) )->handle();

    $product = Product::query()->where( 'sku', 'MUG-1' )->firstOrFail();

    expect( ProductImports::find( $state['id'] ) )
        ->status->toBe( 'completed' )
        ->warnings->toBe( [ 'stock' => 1 ] )
        ->and( ArtisanPackUI\Ecommerce\Models\InventoryItem::query()->where( 'stockable_type', $product->getMorphClass() )->where( 'stockable_id', $product->id )->value( 'quantity_on_hand' ) ?? 0 )->toBe( 0 );
} );
