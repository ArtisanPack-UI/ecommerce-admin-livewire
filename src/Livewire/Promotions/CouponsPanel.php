<?php

/**
 * Promotion coupons panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions;

use ArtisanPackUI\Ecommerce\Models\Coupon;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\Ecommerce\Services\ActivityLogService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Csv;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The codes of a `coupon` promotion (spec §7.5): add, rename, and delete
 * codes, bulk-generate them, and export them to CSV.
 *
 * Codes are unique across the store and stored normalized (trimmed and
 * upper-cased, {@see Coupon::normalize()}), so `welcome10` and `WELCOME10`
 * are the same code. Generated codes use an alphabet without look-alike
 * characters (no 0/O, 1/I/L).
 *
 * The engine records usage per promotion, not per code. A satellite that
 * does track codes can supply per-code counts through the
 * `ap.ecommerceAdminLivewire.coupons.usage` filter
 * (`( array $counts, Promotion $promotion, array $codes )`, returning
 * code => count); the "Uses" column appears when it does.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class CouponsPanel extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithActionToken;
    use WithPagination;

    /**
     * The characters generated codes use: no 0/O, 1/I/L.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * The most codes one generate run makes.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_GENERATE = 1000;

    /**
     * The characters a code may contain once normalized.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const CODE_PATTERN = '/^[A-Z0-9][A-Z0-9_-]*$/D';

    /**
     * The longest code the `coupons.code` column holds.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_LENGTH = 80;

    /**
     * Codes per page.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const PER_PAGE = 25;

    /**
     * The promotion id.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $promotionId = 0;

    /**
     * Filters the code list.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( as: 'code', except: '' )]
    public string $search = '';

    /**
     * The code being added.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $newCode = '';

    /**
     * The code being renamed.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $editingId = null;

    /**
     * The new text of the code being renamed.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $editingCode = '';

    /**
     * The code waiting for delete confirmation.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $deletingId = null;

    /**
     * How many codes to generate.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $generateCount = 10;

    /**
     * Text every generated code starts with.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $generatePrefix = '';

    /**
     * Random characters per generated code (after the prefix).
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $generateLength = 8;

    /**
     * Loads and authorizes the promotion.
     *
     * @since 1.0.0
     *
     * @param  Promotion  $promotion  The promotion.
     *
     * @return void
     */
    public function mount( Promotion $promotion ): void
    {
        $this->authorizeEcommerce( 'view', $promotion );

        $this->promotionId = (int) $promotion->id;
    }

    /**
     * Re-checks access on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeEcommerce( 'view', $this->promotion() );
    }

    /**
     * Back to the first page when the search changes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedSearch(): void
    {
        $this->resetPage( 'codes-page' );
    }

    /**
     * Adds one code.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addCode(): void
    {
        $this->authorizeEcommerce( 'create', Coupon::class );

        $this->newCode = Coupon::normalize( $this->newCode );

        $this->validate(
            [ 'newCode' => self::codeRules() ],
            self::codeMessages(),
            [ 'newCode' => __( 'code' ) ],
        );

        try {
            $coupon = Coupon::query()->create( [ 'promotion_id' => $this->promotionId, 'code' => $this->newCode ] );
        } catch ( UniqueConstraintViolationException ) {
            $this->addError( 'newCode', self::codeMessages()['*.unique'] );

            return;
        }

        $this->newCode = '';
        $this->toastSuccess( __( 'Code :code added.', [ 'code' => $coupon->code ] ) );
    }

    /**
     * Starts renaming a code in its row.
     *
     * @since 1.0.0
     *
     * @param  int  $couponId  The coupon id.
     *
     * @return void
     */
    public function startRename( int $couponId ): void
    {
        $coupon = $this->findCoupon( $couponId );

        if ( null === $coupon ) {
            return;
        }

        $this->authorizeEcommerce( 'update', $coupon );

        $this->resetErrorBag();
        $this->editingId   = (int) $coupon->id;
        $this->editingCode = (string) $coupon->code;
    }

    /**
     * Saves the rename.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveRename(): void
    {
        $coupon = null === $this->editingId ? null : $this->findCoupon( $this->editingId );

        if ( null === $coupon ) {
            $this->cancelRename();

            return;
        }

        $this->authorizeEcommerce( 'update', $coupon );

        $this->editingCode = Coupon::normalize( $this->editingCode );

        $this->validate(
            [ 'editingCode' => self::codeRules( (int) $coupon->id ) ],
            self::codeMessages(),
            [ 'editingCode' => __( 'code' ) ],
        );

        try {
            $coupon->fill( [ 'code' => $this->editingCode ] )->save();
        } catch ( UniqueConstraintViolationException ) {
            $this->addError( 'editingCode', self::codeMessages()['*.unique'] );

            return;
        }

        $this->cancelRename();
        $this->toastSuccess( __( 'Code renamed to :code.', [ 'code' => $coupon->code ] ) );
    }

    /**
     * Stops renaming.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelRename(): void
    {
        $this->editingId   = null;
        $this->editingCode = '';
        $this->resetErrorBag( 'editingCode' );
    }

    /**
     * Asks to confirm deleting a code.
     *
     * @since 1.0.0
     *
     * @param  int  $couponId  The coupon id.
     *
     * @return void
     */
    public function confirmDelete( int $couponId ): void
    {
        $coupon = $this->findCoupon( $couponId );

        if ( null === $coupon ) {
            return;
        }

        $this->authorizeEcommerce( 'delete', $coupon );

        $this->deletingId = (int) $coupon->id;
    }

    /**
     * Closes the delete confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelDelete(): void
    {
        $this->deletingId = null;
    }

    /**
     * Deletes the code waiting for confirmation.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The one-time action token.
     *
     * @return void
     */
    public function deleteCode( string $token ): void
    {
        $coupon = null === $this->deletingId ? null : $this->findCoupon( $this->deletingId );

        $this->deletingId = null;

        if ( null === $coupon ) {
            return;
        }

        $this->authorizeEcommerce( 'delete', $coupon );

        $deleted = $this->withActionToken( $token, 'delete-code', static function () use ( $coupon ): bool {
            $coupon->delete();

            return true;
        }, $this->promotion() );

        if ( true !== $deleted ) {
            return;
        }

        if ( $this->editingId === (int) $coupon->id ) {
            $this->cancelRename();
        }

        $this->toastSuccess( __( 'Code :code deleted. It no longer works at checkout.', [ 'code' => $coupon->code ] ) );
    }

    /**
     * Generates a batch of unique random codes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function generateCodes(): void
    {
        $this->authorizeEcommerce( 'create', Coupon::class );

        $this->generatePrefix = Coupon::normalize( $this->generatePrefix );

        $this->validate(
            [
                'generateCount'  => [ 'required', 'integer', 'min:1', 'max:' . self::MAX_GENERATE ],
                'generatePrefix' => [ 'nullable', 'string', 'max:20', 'regex:/^[A-Z0-9_-]*$/D' ],
                'generateLength' => [ 'required', 'integer', 'min:4', 'max:32' ],
            ],
            [ 'generatePrefix.regex' => __( 'Use only letters, numbers, hyphens, and underscores.' ) ],
            [
                'generateCount'  => __( 'number of codes' ),
                'generatePrefix' => __( 'prefix' ),
                'generateLength' => __( 'length' ),
            ],
        );

        $count  = (int) $this->generateCount;
        $prefix = $this->generatePrefix;
        $length = (int) $this->generateLength;

        if ( strlen( $prefix ) + $length > self::MAX_LENGTH ) {
            $this->addError( 'generateLength', __( 'The prefix and length together can be at most :max characters.', [ 'max' => self::MAX_LENGTH ] ) );

            return;
        }

        $codes = $this->uniqueCodes( $count, $prefix, $length );

        if ( count( $codes ) < $count ) {
            $this->addError( 'generateLength', __( 'Not enough unused codes of this length. Use a longer length or another prefix.' ) );

            return;
        }

        $now       = Carbon::now();
        $promotion = $this->promotion();

        try {
            DB::transaction( function () use ( $codes, $now, $promotion, $prefix, $length ): void {
                foreach ( array_chunk( $codes, 500 ) as $chunk ) {
                    Coupon::query()->insert( array_map(
                        fn ( string $code ): array => [ 'promotion_id' => $this->promotionId, 'code' => $code, 'created_at' => $now, 'updated_at' => $now ],
                        $chunk,
                    ) );
                }

                app( ActivityLogService::class )->record( $promotion, 'coupons.generated', [ 'count' => count( $codes ), 'prefix' => $prefix, 'length' => $length ] );
            } );
        } catch ( UniqueConstraintViolationException ) {
            $this->addError( 'generateCount', __( 'Another code was added with the same text while generating. Nothing was saved; try again.' ) );

            return;
        }

        $this->resetPage( 'codes-page' );
        $this->toastSuccess( trans_choice( ':count code generated.|:count codes generated.', count( $codes ), [ 'count' => count( $codes ) ] ) );
    }

    /**
     * Downloads every code of the promotion as CSV.
     *
     * @since 1.0.0
     *
     * @return StreamedResponse
     */
    public function exportCodes(): StreamedResponse
    {
        $promotion = $this->promotion();
        $rows      = [];

        foreach ( Coupon::query()->where( 'promotion_id', $this->promotionId )->orderBy( 'code' )->lazy( 500 ) as $coupon ) {
            $rows[] = [ (string) $coupon->code, $coupon->created_at?->format( DATE_ATOM ) ?? '' ];
        }

        $csv  = Csv::build( [ __( 'Code' ), __( 'Created' ) ], $rows );
        $name = 'coupons-' . $promotion->key . '-' . Carbon::now()->format( 'Y-m-d-His' ) . '.csv';

        return response()->streamDownload( static function () use ( $csv ): void {
            echo $csv;
        }, $name, [ 'Content-Type' => 'text/csv; charset=UTF-8' ] );
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $promotion = $this->promotion();
        $codes     = $this->codesQuery()->orderBy( 'code' )->paginate( self::PER_PAGE, [ '*' ], 'codes-page' );
        $usage     = (array) applyFilters( 'ap.ecommerceAdminLivewire.coupons.usage', [], $promotion, array_map( static fn ( Coupon $coupon ): string => (string) $coupon->code, $codes->items() ) );

        return view( 'ecommerce-admin::livewire.promotions.coupons', [
            'promotion'   => $promotion,
            'codes'       => $codes,
            'total'       => Coupon::query()->where( 'promotion_id', $this->promotionId )->count(),
            'usage'       => array_filter( $usage, 'is_numeric' ),
            'canCreate'   => $this->canEcommerce( 'create', Coupon::class ),
            'canUpdate'   => $this->canEcommerce( 'update', new Coupon( [ 'promotion_id' => $this->promotionId ] ) ),
            'canDelete'   => $this->canEcommerce( 'delete', new Coupon( [ 'promotion_id' => $this->promotionId ] ) ),
            'deleteToken' => null === $this->deletingId ? null : $this->actionToken( 'delete-code', $promotion ),
            'isCoupon'    => Promotion::SOURCE_COUPON === $promotion->source_type,
        ] );
    }

    /**
     * Validation rules for one code.
     *
     * @since 1.0.0
     *
     * @param  int|null  $ignore  A coupon id the uniqueness check skips.
     *
     * @return array<int, mixed>
     */
    protected static function codeRules( ?int $ignore = null ): array
    {
        return [ 'required', 'string', 'max:' . self::MAX_LENGTH, 'regex:' . self::CODE_PATTERN, Rule::unique( Coupon::class, 'code' )->ignore( $ignore ) ];
    }

    /**
     * Messages for the code rules.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected static function codeMessages(): array
    {
        return [
            '*.regex'  => __( 'Use only letters, numbers, hyphens, and underscores, starting with a letter or number.' ),
            '*.unique' => __( 'That code is already in use, on this or another promotion.' ),
        ];
    }

    /**
     * `$count` random codes that no coupon uses yet.
     *
     * @since 1.0.0
     *
     * @param  int     $count   How many.
     * @param  string  $prefix  Normalized prefix.
     * @param  int     $length  Random characters per code.
     *
     * @return array<int, string>
     */
    protected function uniqueCodes( int $count, string $prefix, int $length ): array
    {
        $codes = [];
        $last  = strlen( self::ALPHABET ) - 1;

        for ( $attempt = 0; $attempt < 10 && count( $codes ) < $count; $attempt++ ) {
            $batch = [];

            for ( $i = 0, $needed = ( $count - count( $codes ) ) * 2; $i < $needed; $i++ ) {
                $code = $prefix;

                for ( $c = 0; $c < $length; $c++ ) {
                    $code .= self::ALPHABET[ random_int( 0, $last ) ];
                }

                if ( ! isset( $codes[ $code ] ) ) {
                    $batch[ $code ] = true;
                }
            }

            $taken = [];

            foreach ( array_chunk( array_keys( $batch ), 500 ) as $chunk ) {
                $taken = [ ...$taken, ...Coupon::query()->whereIn( 'code', $chunk )->pluck( 'code' )->all() ];
            }

            foreach ( array_diff( array_keys( $batch ), $taken ) as $code ) {
                if ( count( $codes ) === $count ) {
                    break;
                }

                $codes[ $code ] = true;
            }
        }

        return array_map( 'strval', array_keys( $codes ) );
    }

    /**
     * The promotion's codes, filtered by the search.
     *
     * @since 1.0.0
     *
     * @return Builder<Coupon>
     */
    protected function codesQuery(): Builder
    {
        $query  = Coupon::query()->where( 'promotion_id', $this->promotionId );
        $search = Coupon::normalize( mb_substr( $this->search, 0, self::MAX_LENGTH ) );

        if ( '' !== $search ) {
            $pattern = '%' . str_replace( [ '\\', '%', '_' ], [ '\\\\', '\\%', '\\_' ], $search ) . '%';

            $query->whereRaw( $query->getQuery()->getGrammar()->wrap( 'code' ) . ' LIKE ? ESCAPE ?', [ $pattern, '\\' ] );
        }

        return $query;
    }

    /**
     * One of the promotion's coupons.
     *
     * @since 1.0.0
     *
     * @param  int  $couponId  The coupon id.
     *
     * @return Coupon|null
     */
    protected function findCoupon( int $couponId ): ?Coupon
    {
        return Coupon::query()->where( 'promotion_id', $this->promotionId )->find( $couponId );
    }

    /**
     * The promotion.
     *
     * @since 1.0.0
     *
     * @return Promotion
     */
    protected function promotion(): Promotion
    {
        return Promotion::query()->findOrFail( $this->promotionId );
    }
}
