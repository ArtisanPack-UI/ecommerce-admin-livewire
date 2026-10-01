<?php

/**
 * Product import job.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Jobs;

use ArtisanPackUI\Ecommerce\Exceptions\ProductWriteException;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Csv;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductCsv;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductImports;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Applies a checked product import, row by row.
 *
 * Each row is its own transaction through `ProductService`, so a bad row
 * fails alone. The resume point is saved after every row: a job that dies
 * part-way is dispatched again and picks up at the next row (a row whose
 * write committed just before the worker died may be applied again; rows
 * match on SKU and slug, so that is an update unless the row has neither).
 * A cache lock keeps one worker per import, so the queue's `retry_after`
 * should exceed {@see self::$timeout}. Each row checks
 * the importing user's `product.create` or `product.update` ability, so an
 * import cannot do more than its owner could by hand.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class RunProductImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Seconds the job may run.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public int $timeout = 3600;

    /**
     * Attempts (a failed import is resumed by hand).
     *
     * @since 1.0.0
     *
     * @var int
     */
    public int $tries = 1;

    /**
     * @since 1.0.0
     *
     * @param  string  $importId  Import id.
     */
    public function __construct( public string $importId )
    {
    }

    /**
     * Applies the rows from the resume point on, holding a lock so only one
     * worker runs an import at a time.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function handle(): void
    {
        $lock = Cache::lock( ProductImports::lockKey( $this->importId ), $this->timeout + 60 );

        if ( ! $lock->get() ) {
            // Another worker is already applying this import.
            return;
        }

        try {
            $this->run();
        } finally {
            $lock->release();
        }
    }

    /**
     * Marks the import failed so it can be resumed, unless another worker
     * still holds it (a queue that re-released a long-running job).
     *
     * @since 1.0.0
     *
     * @param  Throwable|null  $exception  Cause.
     *
     * @return void
     */
    public function failed( ?Throwable $exception ): void
    {
        $lock = Cache::lock( ProductImports::lockKey( $this->importId ), 10 );

        if ( ! $lock->get() ) {
            return;
        }

        try {
            $state = ProductImports::find( $this->importId );

            if ( null !== $state && 'completed' !== $state['status'] ) {
                $this->finish( $state, 'failed', __( 'The import stopped part-way. Resume it to carry on from the next row.' ) );
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Applies the rows from the resume point on.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function run(): void
    {
        $state = ProductImports::find( $this->importId );

        if ( null === $state || ! in_array( $state['status'], ProductImports::ACTIVE, true ) ) {
            return;
        }

        $user = Auth::getProvider()?->retrieveById( $state['user_id'] );

        if ( ! $user instanceof Authenticatable || ( ! Authorization::allows( $user, 'product.create' ) && ! Authorization::allows( $user, 'product.update' ) ) ) {
            $this->finish( $state, 'failed', __( 'The person who started this import can no longer edit products.' ) );

            return;
        }

        if ( ! ProductImports::hasSource( $state ) ) {
            $this->finish( $state, 'failed', __( 'The uploaded file is gone. Start the import again.' ) );

            return;
        }

        $state['status'] = 'running';
        $state           = ProductImports::save( $state );

        $state = ProductImports::withSource( $state, function ( string $path ) use ( $state, $user ): array {
            foreach ( Csv::rows( $path, (int) $state['processed'] ) as $row ) {
                $state = ProductImports::save( $this->applyRow( $state, $row, $user ) );
            }

            return $state;
        } );

        ProductImports::deleteSource( $state );
        $this->finish( $state, 'completed', null );
    }

    /**
     * Applies one row in its own transaction and records the outcome.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>       $state  State.
     * @param  array<string, int|string>  $row    Row keyed by header.
     * @param  Authenticatable            $user   Importing user.
     *
     * @return array<string, mixed>
     */
    protected function applyRow( array $state, array $row, Authenticatable $user ): array
    {
        $mapped = ProductImports::mapRow( $row, (array) $state['mapping'] );

        try {
            $action = DB::transaction( static fn (): string => ProductCsv::apply( $mapped, $user ) );

            ++$state['counts'][ str_starts_with( $action, 'create' ) ? 'created' : 'updated' ];
        } catch ( InvalidArgumentException|ProductWriteException $exception ) {
            $state = self::recordError( $state, $row, $mapped, $exception->getMessage() );
        } catch ( Throwable $exception ) {
            report( $exception );

            $state = self::recordError( $state, $row, $mapped, __( 'This row could not be saved.' ) );
        }

        ++$state['processed'];

        return $state;
    }

    /**
     * Adds a failed row to the state.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>       $state    State.
     * @param  array<string, int|string>  $row      Row keyed by header.
     * @param  array<string, string>      $mapped   Row keyed by column.
     * @param  string                     $message  Why.
     *
     * @return array<string, mixed>
     */
    private static function recordError( array $state, array $row, array $mapped, string $message ): array
    {
        ++$state['counts']['failed'];

        $state['errors'][] = [
            'line'    => (int) ( $row['__line'] ?? 0 ),
            'label'   => ProductCsv::label( $mapped ),
            'message' => $message,
        ];

        return $state;
    }

    /**
     * Saves a final status.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $state    State.
     * @param  string                $status   `completed` or `failed`.
     * @param  string|null           $message  Shown to the user.
     *
     * @return void
     */
    private function finish( array $state, string $status, ?string $message ): void
    {
        $state['status']  = $status;
        $state['message'] = $message;

        ProductImports::save( $state );
    }
}
