<?php

/**
 * Reviews moderation queue.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Reviews;

use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\ProductReview;
use ArtisanPackUI\Ecommerce\Services\ReviewService;
use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithResourceTable;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ResourceQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\ReviewsQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The reviews moderation queue (spec §7.2).
 *
 * - Opens on `pending` reviews. Columns: product, rating, author,
 *   verified-purchase badge, excerpt, status, and submitted date.
 * - Filters: status, rating, product, verified purchases only.
 * - Row and bulk actions approve, reject (with a reason), mark as spam,
 *   requeue, and delete, all through the engine's `ReviewService`.
 * - A drawer shows the whole review and its media. Review text is always
 *   escaped: it is customer input.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Index extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithActionToken;
    use WithResourceTable;

    /**
     * Moderation actions and the engine action each one runs.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public const MODERATIONS = [
        'approve' => 'approve',
        'spam'    => 'spam',
        'requeue' => 'pending',
    ];

    /**
     * The review open in the drawer.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $viewingId = null;

    /**
     * Whether the drawer is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $viewing = false;

    /**
     * The review waiting for a rejection reason.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $rejectingId = null;

    /**
     * Whether the reject dialog is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $rejecting = false;

    /**
     * Why the review is rejected (the row action and the bulk action share it).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $rejectReason = '';

    /**
     * Authorizes the screen and opens on pending reviews.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->authorizeTable();

        if ( ! array_key_exists( 'status', $this->filters ) ) {
            $this->filters['status'] = ProductReview::STATUS_PENDING;
        }
    }

    /**
     * Opens a review in the drawer.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Review id.
     *
     * @return void
     */
    public function showReview( int $id ): void
    {
        $review = ProductReview::query()->find( $id );

        if ( null === $review ) {
            $this->authorizeEcommerceAbility( 'review.view' );

            return;
        }

        $this->authorizeEcommerce( 'view', $review );

        $this->viewingId = (int) $review->id;
        $this->viewing   = true;
    }

    /**
     * Approves, marks as spam, or requeues one review.
     *
     * @since 1.0.0
     *
     * @param  int     $id      Review id.
     * @param  string  $action  `approve`, `spam`, or `requeue`.
     *
     * @return void
     */
    public function moderate( int $id, string $action ): void
    {
        $this->authorizeEcommerceAbility( 'review.moderate' );

        $review = ProductReview::query()->find( $id );

        if ( null === $review || ! array_key_exists( $action, self::MODERATIONS ) ) {
            return;
        }

        $this->authorizeEcommerce( 'moderate', $review );

        app( ReviewService::class )->moderate( $review, self::MODERATIONS[ $action ], null, $this->actorId() );

        $this->toastSuccess( self::message( $action, 1 ) );
        $this->dispatch( 'ecommerce-admin-nav-refresh' );
    }

    /**
     * Asks for the reason to reject one review.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Review id.
     *
     * @return void
     */
    public function startReject( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'review.moderate' );

        $this->resetErrorBag();
        $this->rejectingId  = ProductReview::query()->whereKey( $id )->exists() ? $id : null;
        $this->rejecting    = null !== $this->rejectingId;
        $this->rejectReason = '';
    }

    /**
     * Rejects the review waiting for a reason.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function reject(): void
    {
        $this->authorizeEcommerceAbility( 'review.moderate' );

        $review = null === $this->rejectingId || ! $this->rejecting ? null : ProductReview::query()->find( $this->rejectingId );

        if ( null === $review ) {
            $this->closeReject();

            return;
        }

        $this->authorizeEcommerce( 'moderate', $review );
        $this->validateReason();

        app( ReviewService::class )->reject( $review, sanitizeText( $this->rejectReason ), $this->actorId() );

        $this->closeReject();
        $this->toastSuccess( self::message( 'reject', 1 ) );
        $this->dispatch( 'ecommerce-admin-nav-refresh' );
    }

    /**
     * Closes the reject dialog.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function closeReject(): void
    {
        $this->rejecting    = false;
        $this->rejectingId  = null;
        $this->rejectReason = '';
    }

    /**
     * Asks to confirm deleting one review (through the bulk delete).
     *
     * @since 1.0.0
     *
     * @param  int  $id  Review id.
     *
     * @return void
     */
    public function confirmDelete( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'review.delete' );

        $this->selected          = [ $id ];
        $this->selectAllMatching = false;

        $this->runBulkAction( 'delete' );
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $user      = auth()->user();
        $reviewing = null === $this->viewingId || ! $this->viewing
            ? null
            : ProductReview::query()->with( [ 'product:id,name', 'order:id,order_number', 'media' ] )->find( $this->viewingId );

        return view( 'ecommerce-admin::livewire.reviews.index', $this->resourceTableData() + [
            'canModerate'  => Authorization::allows( $user, 'review.moderate' ),
            'canDelete'    => Authorization::allows( $user, 'review.delete' ),
            'canView'      => Authorization::allows( $user, 'review.view' ),
            'reviewing'    => $reviewing,
            'reviewMedia'  => null === $reviewing ? [] : $reviewing->media->map( static fn ( $media ): array => [
                'id'  => (int) $media->media_id,
                'url' => ProductMedia::safeUrl( ProductMedia::mediaUrl( (int) $media->media_id, 'medium' ) ),
            ] )->all(),
        ] );
    }

    /**
     * The label of a review status.
     *
     * @since 1.0.0
     *
     * @param  string  $status  Status.
     *
     * @return string
     */
    public static function statusLabel( string $status ): string
    {
        return self::statuses()[ $status ] ?? $status;
    }

    /**
     * Review statuses and their labels.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            ProductReview::STATUS_PENDING  => __( 'Pending' ),
            ProductReview::STATUS_APPROVED => __( 'Approved' ),
            ProductReview::STATUS_REJECTED => __( 'Rejected' ),
            ProductReview::STATUS_SPAM     => __( 'Spam' ),
        ];
    }

    /**
     * The first words of a review, for the table.
     *
     * @since 1.0.0
     *
     * @param  ProductReview  $review  Review.
     *
     * @return string
     */
    public static function excerpt( ProductReview $review ): string
    {
        $text = trim( (string) ( $review->title ?? '' ) );
        $body = trim( preg_replace( '/\s+/', ' ', strip_tags( (string) ( $review->body ?? '' ) ) ) );

        return Str::limit( '' === $text ? $body : $text . ' — ' . $body, 100 );
    }

    /**
     * Runs a moderation action on the selection.
     *
     * @since 1.0.0
     *
     * @param  Builder<ProductReview>  $selection  Selected reviews.
     * @param  string                  $action     `approve`, `reject`, `spam`, or `requeue`.
     *
     * @return string|null
     */
    protected function moderateSelection( Builder $selection, string $action ): ?string
    {
        if ( 'reject' === $action ) {
            $this->validateReason();
        }

        $service = app( ReviewService::class );
        $reason  = 'reject' === $action ? sanitizeText( $this->rejectReason ) : null;
        $engine  = 'reject' === $action ? 'reject' : self::MODERATIONS[ $action ];
        $ids     = ( clone $selection )->reorder()->pluck( $selection->qualifyColumn( 'id' ) )->all();
        $changed = 0;
        $denied  = 0;

        DB::transaction( function () use ( $ids, $service, $engine, $reason, &$changed, &$denied ): void {
            foreach ( array_chunk( $ids, 200 ) as $chunk ) {
                foreach ( ProductReview::query()->whereKey( $chunk )->get() as $review ) {
                    if ( ! $this->canEcommerce( 'moderate', $review ) ) {
                        ++$denied;
                        continue;
                    }

                    $service->moderate( $review, $engine, $reason, $this->actorId() );
                    ++$changed;
                }
            }
        } );

        if ( 'reject' === $action ) {
            $this->rejectReason = '';
        }

        $this->dispatch( 'ecommerce-admin-nav-refresh' );

        return self::withDeniedNote( self::message( $action, $changed ), $denied );
    }

    /**
     * Deletes the selected reviews.
     *
     * @since 1.0.0
     *
     * @param  Builder<ProductReview>  $selection  Selected reviews.
     *
     * @return string|null
     */
    protected function deleteSelection( Builder $selection ): ?string
    {
        $service = app( ReviewService::class );
        $ids     = ( clone $selection )->reorder()->pluck( $selection->qualifyColumn( 'id' ) )->all();
        $deleted = 0;
        $denied  = 0;

        DB::transaction( function () use ( $ids, $service, &$deleted, &$denied ): void {
            foreach ( array_chunk( $ids, 200 ) as $chunk ) {
                foreach ( ProductReview::query()->whereKey( $chunk )->get() as $review ) {
                    if ( ! $this->canEcommerce( 'delete', $review ) ) {
                        ++$denied;
                        continue;
                    }

                    $service->delete( $review );
                    ++$deleted;
                }
            }
        } );

        if ( null !== $this->viewingId && in_array( $this->viewingId, array_map( 'intval', $ids ), true ) ) {
            $this->viewing   = false;
            $this->viewingId = null;
        }

        $this->dispatch( 'ecommerce-admin-nav-refresh' );

        return self::withDeniedNote( trans_choice( ':count review deleted.|:count reviews deleted.', $deleted, [ 'count' => $deleted ] ), $denied );
    }

    /**
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeTable(): void
    {
        $this->authorizeEcommerce( 'viewAny', ProductReview::class );
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableScreen(): string
    {
        return 'reviews';
    }

    /**
     * @since 1.0.0
     *
     * @return ResourceQuery
     */
    protected function tableQuery(): ResourceQuery
    {
        return new ReviewsQuery();
    }

    /**
     * @since 1.0.0
     *
     * @return string
     */
    protected function tableCaption(): string
    {
        return __( 'Reviews' );
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableColumns(): array
    {
        $cells = 'ecommerce-admin::livewire.reviews.cells.';

        return [
            [
                'key'    => 'product',
                'label'  => __( 'Product' ),
                'view'   => $cells . 'product',
                'export' => static fn ( ProductReview $review ): string => (string) ( $review->product?->name ?? '' ),
            ],
            [
                'key'      => 'rating',
                'label'    => __( 'Rating' ),
                'sortable' => true,
                'view'     => $cells . 'rating',
                'export'   => static fn ( ProductReview $review ): int => (int) $review->rating,
            ],
            [
                'key'    => 'author',
                'label'  => __( 'Author' ),
                'view'   => $cells . 'author',
                'export' => static fn ( ProductReview $review ): string => (string) $review->author_name,
            ],
            [
                'key'    => 'verified',
                'label'  => __( 'Verified purchase' ),
                'view'   => $cells . 'verified',
                'export' => static fn ( ProductReview $review ): int => $review->is_verified_purchase ? 1 : 0,
            ],
            [
                'key'   => 'excerpt',
                'label' => __( 'Review' ),
                'value' => static fn ( ProductReview $review ): string => self::excerpt( $review ),
            ],
            [
                'key'    => 'status',
                'label'  => __( 'Status' ),
                'view'   => $cells . 'status',
                'export' => static fn ( ProductReview $review ): string => self::statusLabel( (string) $review->status ),
            ],
            [
                'key'      => 'submitted',
                'label'    => __( 'Submitted' ),
                'sortable' => true,
                'value'    => static fn ( ProductReview $review ): string => null === $review->created_at ? '' : LocalizedDate::format( $review->created_at ),
                'export'   => static fn ( ProductReview $review ): string => $review->created_at?->format( DATE_ATOM ) ?? '',
            ],
            [
                'key'        => 'actions',
                'label'      => __( 'Actions' ),
                'class'      => 'text-end',
                'view'       => $cells . 'actions',
                'exportable' => false,
            ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableFilters(): array
    {
        $statuses = [];

        foreach ( self::statuses() as $id => $name ) {
            $statuses[] = [ 'id' => $id, 'name' => $name ];
        }

        return [
            [ 'key' => 'status', 'label' => __( 'Status' ), 'type' => 'select', 'options' => $statuses ],
            [
                'key'     => 'rating',
                'label'   => __( 'Rating' ),
                'type'    => 'select',
                'options' => array_map(
                    static fn ( int $stars ): array => [ 'id' => (string) $stars, 'name' => trans_choice( ':count star|:count stars', $stars, [ 'count' => $stars ] ) ],
                    [ 5, 4, 3, 2, 1 ],
                ),
            ],
            [ 'key' => 'product', 'label' => __( 'Product' ), 'type' => 'select', 'options' => self::productOptions() ],
            [ 'key' => 'verified', 'label' => __( 'Verified purchases only' ), 'type' => 'boolean' ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableBulkActions(): array
    {
        return [
            [
                'key'     => 'approve',
                'label'   => __( 'Approve' ),
                'icon'    => 'o-check',
                'ability' => 'review.moderate',
                'handler' => fn ( Builder $selection ): ?string => $this->moderateSelection( $selection, 'approve' ),
            ],
            [
                'key'     => 'reject',
                'label'   => __( 'Reject' ),
                'icon'    => 'o-x-mark',
                'ability' => 'review.moderate',
                'handler' => fn ( Builder $selection ): ?string => $this->moderateSelection( $selection, 'reject' ),
            ],
            [
                'key'     => 'spam',
                'label'   => __( 'Mark as spam' ),
                'icon'    => 'o-no-symbol',
                'ability' => 'review.moderate',
                'handler' => fn ( Builder $selection ): ?string => $this->moderateSelection( $selection, 'spam' ),
            ],
            [
                'key'     => 'requeue',
                'label'   => __( 'Back to pending' ),
                'icon'    => 'o-arrow-uturn-left',
                'ability' => 'review.moderate',
                'handler' => fn ( Builder $selection ): ?string => $this->moderateSelection( $selection, 'requeue' ),
            ],
            [
                'key'     => 'delete',
                'label'   => __( 'Delete' ),
                'icon'    => 'o-trash',
                'ability' => 'review.delete',
                'confirm' => __( 'Delete the selected reviews? This cannot be undone.' ),
                'handler' => fn ( Builder $selection ): ?string => $this->deleteSelection( $selection ),
            ],
            $this->exportBulkAction(),
        ];
    }

    /**
     * Validates the rejection reason.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function validateReason(): void
    {
        $this->validate(
            [ 'rejectReason' => [ 'required', 'string', 'max:500' ] ],
            [],
            [ 'rejectReason' => __( 'reason' ) ],
        );
    }

    /**
     * The acting user's id.
     *
     * @since 1.0.0
     *
     * @return int|null
     */
    protected function actorId(): ?int
    {
        $id = auth()->id();

        return is_numeric( $id ) ? (int) $id : null;
    }

    /**
     * The success message for a moderation action.
     *
     * @since 1.0.0
     *
     * @param  string  $action  Action.
     * @param  int     $count   Reviews changed.
     *
     * @return string
     */
    private static function message( string $action, int $count ): string
    {
        return match ( $action ) {
            'approve' => trans_choice( ':count review approved.|:count reviews approved.', $count, [ 'count' => $count ] ),
            'reject'  => trans_choice( ':count review rejected.|:count reviews rejected.', $count, [ 'count' => $count ] ),
            'spam'    => trans_choice( ':count review marked as spam.|:count reviews marked as spam.', $count, [ 'count' => $count ] ),
            default   => trans_choice( ':count review moved back to pending.|:count reviews moved back to pending.', $count, [ 'count' => $count ] ),
        };
    }

    /**
     * Products that have reviews, by name.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    private static function productOptions(): array
    {
        return Product::query()
            ->whereIn( 'id', ProductReview::query()->select( 'product_id' ) )
            ->orderBy( 'name' )
            ->get( [ 'id', 'name' ] )
            ->map( static fn ( Product $product ): array => [ 'id' => (string) $product->id, 'name' => (string) $product->name ] )
            ->all();
    }
}
