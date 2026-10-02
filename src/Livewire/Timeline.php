<?php

/**
 * Activity timeline component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\Ecommerce\Models\OrderTimelineEntry;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\Ecommerce\Services\ActivityLogService;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Support\StatusPresenter;
use ArtisanPackUI\EcommerceAdminLivewire\Support\UserNames;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The activity timeline (spec §8.2): one component for every entity.
 *
 * Orders read the engine's `order_timeline_entries`; products, customers,
 * and promotions read the engine's activity log. Entries render newest
 * first with the actor, the time, an icon, and a one-line description.
 * Satellites describe their own event types through the
 * `ap.ecommerceAdminLivewire.timeline.entry` filter; anything still
 * undescribed falls back to its raw type with a collapsible payload.
 *
 * Only {@see self::SUBJECTS} can be shown; any other model is a developer
 * error and throws {@see InvalidArgumentException} on mount.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Timeline extends Component
{
    use AuthorizesEcommerce;

    /**
     * Models the timeline can show.
     *
     * @since 1.0.0
     *
     * @var array<int, class-string<Model>>
     */
    public const SUBJECTS = [ Order::class, Product::class, Customer::class, Promotion::class ];

    /**
     * The filter values.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const FILTERS = [ 'all', 'notes', 'system' ];

    /**
     * Entries shown at first and added by each "load more".
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const PAGE_SIZE = 20;

    /**
     * The subject's class.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $subjectType = '';

    /**
     * The subject's key.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $subjectId = 0;

    /**
     * `all`, `notes`, or `system`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $filter = 'all';

    /**
     * How many entries to show.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $limit = self::PAGE_SIZE;

    /**
     * Re-render when an order panel changes the order or a note is added
     * or deleted.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    protected $listeners = [
        'ecommerce-admin-order-updated' => '$refresh',
        Notes::NOTES_CHANGED_EVENT      => '$refresh',
    ];

    /**
     * The subject, loaded once per request.
     *
     * @since 1.0.0
     *
     * @var Model|null
     */
    private ?Model $loadedSubject = null;

    /**
     * Boards and sub-statuses referenced by the entries on screen.
     *
     * @since 1.0.0
     *
     * @var array{boards: array<int, string>, substatuses: array<int, string>}
     */
    private array $lookups = [ 'boards' => [], 'substatuses' => [] ];

    /**
     * Stores and authorizes the subject.
     *
     * @since 1.0.0
     *
     * @param  Model  $subject  An order, product, customer, or promotion.
     *
     * @throws InvalidArgumentException When the subject type is not supported.
     *
     * @return void
     */
    public function mount( Model $subject ): void
    {
        if ( ! in_array( $subject::class, self::SUBJECTS, true ) ) {
            throw new InvalidArgumentException( sprintf( 'The timeline cannot show a %s.', $subject::class ) );
        }

        $this->subjectType   = $subject::class;
        $this->subjectId     = (int) $subject->getKey();
        $this->loadedSubject = $subject;

        $this->authorizeEcommerce( 'view', $subject );
    }

    /**
     * Re-authorizes on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeEcommerce( 'view', $this->subject() );
    }

    /**
     * Shows another page of entries.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function loadMore(): void
    {
        $this->authorizeEcommerce( 'view', $this->subject() );

        $this->limit = max( self::PAGE_SIZE, $this->limit ) + self::PAGE_SIZE;
    }

    /**
     * Renders the component.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        if ( ! in_array( $this->filter, self::FILTERS, true ) ) {
            $this->filter = 'all';
        }

        $subject = $this->subject();
        $rows    = $this->query( $subject )->limit( max( 1, $this->limit ) + 1 )->get();
        $hasMore = $rows->count() > max( 1, $this->limit );
        $rows    = $rows->take( max( 1, $this->limit ) );

        $this->loadLookups( $rows->all() );

        $actors = $this->actorNames( $rows->pluck( 'actor_user_id' )->filter()->map( static fn ( mixed $id ): int => (int) $id )->unique()->values()->all() );

        $entries = $rows->map( fn ( Model $row ): array => $this->present( $row, $subject, $actors ) )->all();

        return view( 'ecommerce-admin::livewire.timeline', [
            'entries' => $entries,
            'hasMore' => $hasMore,
            'filters' => [
                'all'    => __( 'All' ),
                'notes'  => __( 'Notes' ),
                'system' => __( 'System events' ),
            ],
        ] );
    }

    /**
     * The subject, loaded once per request.
     *
     * @since 1.0.0
     *
     * @return Model
     */
    protected function subject(): Model
    {
        if ( null !== $this->loadedSubject ) {
            return $this->loadedSubject;
        }

        if ( ! in_array( $this->subjectType, self::SUBJECTS, true ) ) {
            $this->denyEcommerce();
        }

        /** @var class-string<Model> $class */
        $class = $this->subjectType;

        return $this->loadedSubject = $class::query()->findOrFail( $this->subjectId );
    }

    /**
     * The entries query for the subject and filter, newest first.
     *
     * @since 1.0.0
     *
     * @param  Model  $subject  The subject.
     *
     * @return Builder<Model>
     */
    protected function query( Model $subject ): Builder
    {
        $query = $subject instanceof Order
            ? OrderTimelineEntry::query()->where( 'order_id', $subject->getKey() )->orderByDesc( 'created_at' )->orderByDesc( 'id' )
            : app( ActivityLogService::class )->forSubject( $subject );

        return match ( $this->filter ) {
            'notes'  => $query->where( 'event_type', 'like', 'note.%' ),
            'system' => $query->where( 'event_type', 'not like', 'note.%' ),
            default  => $query,
        };
    }

    /**
     * Presents one entry for the view.
     *
     * @since 1.0.0
     *
     * @param  Model                  $row      An OrderTimelineEntry or ActivityLogEntry.
     * @param  Model                  $subject  The subject.
     * @param  array<int, string>     $actors   Actor names by user id.
     *
     * @return array{id: int, type: string, icon: string, description: string, known: bool, actor: string, time: Carbon|null, payload: string|null, isNote: bool}
     */
    protected function present( Model $row, Model $subject, array $actors ): array
    {
        $type    = (string) $row->getAttribute( 'event_type' );
        $payload = (array) ( $row->getAttribute( 'payload' ) ?? [] );
        $known   = $this->describe( $type, $payload, $subject );

        $presented = [
            'icon'        => $known['icon'] ?? null,
            'description' => $known['description'] ?? null,
            'known'       => null !== $known,
        ];

        $filtered = applyFilters( 'ap.ecommerceAdminLivewire.timeline.entry', $presented, $row, $subject );

        if ( is_array( $filtered ) ) {
            $description = $filtered['description'] ?? null;
            $icon        = $filtered['icon'] ?? null;

            $presented = [
                'icon'        => is_string( $icon ) && '' !== $icon ? $icon : $presented['icon'],
                'description' => is_string( $description ) && '' !== trim( $description ) ? $description : null,
                'known'       => ( $filtered['known'] ?? false ) === true || ( is_string( $description ) && '' !== trim( $description ) ),
            ];
        }

        $known    = $presented['known'] && null !== $presented['description'];
        $actorId  = $row->getAttribute( 'actor_user_id' );
        $created  = $row->getAttribute( 'created_at' );
        $rendered = null;

        if ( ! $known ) {
            $json     = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            $rendered = false === $json ? '' : $json;
        }

        return [
            'id'          => (int) $row->getKey(),
            'type'        => $type,
            'icon'        => $presented['icon'] ?? 'o-information-circle',
            'description' => $known ? (string) $presented['description'] : $type,
            'known'       => $known,
            'actor'       => null === $actorId ? __( 'System' ) : ( $actors[ (int) $actorId ] ?? __( 'User #:id', [ 'id' => (int) $actorId ] ) ),
            'time'        => $created instanceof Carbon ? $created : ( null === $created ? null : Carbon::parse( (string) $created ) ),
            'payload'     => $rendered,
            'isNote'      => str_starts_with( $type, 'note.' ),
        ];
    }

    /**
     * The icon and description of a known event type, or null.
     *
     * @since 1.0.0
     *
     * @param  string                $type     Event type.
     * @param  array<string, mixed>  $payload  Payload.
     * @param  Model                 $subject  The subject.
     *
     * @return array{icon: string, description: string}|null
     */
    protected function describe( string $type, array $payload, Model $subject ): ?array
    {
        $currency = (string) ( $payload['currency'] ?? ( $subject instanceof Order ? $subject->currency : '' ) );
        $reason   = $this->withReason( $payload );

        $entry = match ( $type ) {
            'order.placed'            => [ 'o-shopping-bag', __( 'Order placed' ) . $this->amountSuffix( $payload['total'] ?? null, $currency ) ],
            'order.status_changed'    => [ 'o-arrow-path', __( 'Status changed from :from to :to', [
                'from' => $this->statusLabel( $payload['from'] ?? null ),
                'to'   => $this->statusLabel( $payload['to'] ?? null ),
            ] ) . $reason ],
            'order.substatus_changed'    => [ 'o-tag', $this->describeSubstatus( $payload ) . $reason ],
            'order.cancelled'            => [ 'o-x-circle', $this->describeCancel( $payload, $currency ) ],
            'order.refunded'             => [ 'o-receipt-refund', __( 'Refunded :amount', [ 'amount' => $this->money( $payload['amount'] ?? 0, $currency ) ] ) . $reason ],
            'order.edited'               => [ 'o-pencil-square', $this->describeEdit( $payload, $currency ) ],
            'note.added'                 => [ 'o-chat-bubble-left-ellipsis', $this->describeNote( $payload, true ) ],
            'note.deleted'               => [ 'o-trash', $this->describeNote( $payload, false ) ],
            'payment.authorized'         => [ 'o-credit-card', __( 'Payment of :amount authorized via :gateway', [ 'amount' => $this->money( $payload['amount'] ?? 0, $currency ), 'gateway' => (string) ( $payload['gateway'] ?? '' ) ] ) ],
            'payment.captured'           => [ 'o-banknotes', __( 'Payment of :amount captured via :gateway', [ 'amount' => $this->money( $payload['amount'] ?? 0, $currency ), 'gateway' => (string) ( $payload['gateway'] ?? '' ) ] ) ],
            'payment.capture_failed'     => [ 'o-exclamation-triangle', __( 'Payment capture failed: :message', [ 'message' => (string) ( $payload['message'] ?? __( 'unknown error' ) ) ] ) ],
            'payment.fraud_blocked'      => [ 'o-shield-exclamation', __( 'Blocked by the fraud check (score :score)', [ 'score' => (string) ( $payload['score'] ?? '—' ) ] ) ],
            'payment.fraud_challenged'   => [ 'o-shield-exclamation', __( 'Challenged by the fraud check (score :score)', [ 'score' => (string) ( $payload['score'] ?? '—' ) ] ) ],
            'kanban.assignment_added'    => [ 'o-view-columns', __( 'Added to board ":board"', [ 'board' => $this->boardName( $payload ) ] ) ],
            'kanban.assignment_removed'  => [ 'o-view-columns', __( 'Removed from board ":board"', [ 'board' => $this->boardName( $payload ) ] ) ],
            'kanban.automation_fired'    => [ 'o-bolt', __( 'Automation ":trigger" ran on board ":board"', [ 'trigger' => (string) ( $payload['trigger_key'] ?? '' ), 'board' => $this->boardName( $payload ) ] ) ],
            'kanban.automation_failed'   => [ 'o-bolt-slash', __( 'Automation ":trigger" failed: :error', [ 'trigger' => (string) ( $payload['trigger_key'] ?? '' ), 'error' => (string) ( $payload['error'] ?? __( 'unknown error' ) ) ] ) ],
            'kanban.order_field_updated' => [ 'o-bolt', __( 'An automation updated the order\'s :field', [ 'field' => (string) ( $payload['field'] ?? '' ) ] ) ],
            'product.created'            => [ 'o-cube', __( 'Product ":name" created', [ 'name' => (string) ( $payload['name'] ?? '' ) ] ) ],
            'product.updated'            => [ 'o-cube', $this->describeChanges( __( 'Product changed' ), $payload ) ],
            'product.deleted'            => [ 'o-trash', __( 'Product ":name" deleted', [ 'name' => (string) ( $payload['name'] ?? '' ) ] ) ],
            'variant.created'            => [ 'o-squares-2x2', __( 'Variant :variant added', [ 'variant' => $this->variantLabel( $payload ) ] ) ],
            'variant.updated'            => [ 'o-squares-2x2', $this->describeChanges( __( 'Variant :variant changed', [ 'variant' => $this->variantLabel( $payload ) ] ), $payload ) ],
            'variant.deleted'            => [ 'o-trash', __( 'Variant :variant removed', [ 'variant' => $this->variantLabel( $payload ) ] ) ],
            'price.created'              => [ 'o-currency-dollar', __( 'Price added: :amount', [ 'amount' => $this->money( $payload['amount'] ?? 0, $currency ) ] ) ],
            'price.updated'              => [ 'o-currency-dollar', $this->describeChanges( __( 'Price changed' ), $payload ) ],
            'price.deleted'              => [ 'o-trash', __( 'Price removed: :amount', [ 'amount' => $this->money( $payload['amount'] ?? 0, $currency ) ] ) ],
            'inventory.adjusted'         => [ 'o-archive-box', $this->describeInventory( $payload ) ],
            'customer.created'           => [ 'o-user-plus', __( 'Customer created' ) ],
            'customer.updated'           => [ 'o-user', $this->describeChanges( __( 'Customer changed' ), $payload ) ],
            'customer.deleted'           => [ 'o-user-minus', __( 'Customer deleted' ) ],
            'address.added'              => [ 'o-map-pin', __( 'Address added' ) ],
            'address.updated'            => [ 'o-map-pin', $this->describeFields( __( 'Address changed' ), $payload ) ],
            'address.deleted'            => [ 'o-trash', __( 'Address deleted' ) ],
            'promotion.created'          => [ 'o-megaphone', __( 'Promotion ":name" created', [ 'name' => (string) ( $payload['name'] ?? '' ) ] ) ],
            'promotion.updated'          => [ 'o-megaphone', $this->describeChanges( __( 'Promotion changed' ), $payload ) ],
            'promotion.deleted'          => [ 'o-trash', __( 'Promotion ":name" deleted', [ 'name' => (string) ( $payload['name'] ?? '' ) ] ) ],
            'coupon.created'             => [ 'o-ticket', __( 'Coupon :code added', [ 'code' => (string) ( $payload['code'] ?? '' ) ] ) ],
            'coupon.updated'             => [ 'o-ticket', $this->describeChanges( __( 'Coupon :code changed', [ 'code' => (string) ( $payload['code'] ?? '' ) ] ), $payload ) ],
            'coupon.deleted'             => [ 'o-trash', __( 'Coupon :code removed', [ 'code' => (string) ( $payload['code'] ?? '' ) ] ) ],
            'coupons.generated'          => [ 'o-sparkles', trans_choice( ':count coupon code generated|:count coupon codes generated', (int) ( $payload['count'] ?? 0 ), [ 'count' => (int) ( $payload['count'] ?? 0 ) ] ) ],
            default                      => null,
        };

        return null === $entry ? null : [ 'icon' => $entry[0], 'description' => $entry[1] ];
    }

    /**
     * Loads the board and sub-status names the entries refer to.
     *
     * @since 1.0.0
     *
     * @param  array<int, Model>  $rows  Entries.
     *
     * @return void
     */
    protected function loadLookups( array $rows ): void
    {
        $boardIds     = [];
        $substatusIds = [];

        foreach ( $rows as $row ) {
            $payload = (array) ( $row->getAttribute( 'payload' ) ?? [] );

            foreach ( [ 'board_id' ] as $key ) {
                if ( isset( $payload[ $key ] ) && is_numeric( $payload[ $key ] ) ) {
                    $boardIds[] = (int) $payload[ $key ];
                }
            }

            foreach ( [ 'from_id', 'to_id', 'substatus_id' ] as $key ) {
                if ( isset( $payload[ $key ] ) && is_numeric( $payload[ $key ] ) ) {
                    $substatusIds[] = (int) $payload[ $key ];
                }
            }
        }

        $this->lookups = [
            'boards'      => [] === $boardIds ? [] : KanbanBoard::query()->whereIn( 'id', array_unique( $boardIds ) )->pluck( 'name', 'id' )->map( static fn ( mixed $name ): string => (string) $name )->all(),
            'substatuses' => [] === $substatusIds ? [] : OrderSubstatus::query()->whereIn( 'id', array_unique( $substatusIds ) )->pluck( 'label', 'id' )->map( static fn ( mixed $label ): string => (string) $label )->all(),
        ];
    }

    /**
     * Actor names by user id, from the host's user model.
     *
     * @since 1.0.0
     *
     * @param  array<int, int>  $ids  User ids.
     *
     * @return array<int, string>
     */
    protected function actorNames( array $ids ): array
    {
        return UserNames::for( $ids );
    }

    /**
     * ` — reason` when the payload carries one.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload  Payload.
     *
     * @return string
     */
    protected function withReason( array $payload ): string
    {
        $reason = $payload['reason'] ?? null;

        return is_string( $reason ) && '' !== trim( $reason ) ? ' — ' . __( 'Reason: :reason', [ 'reason' => trim( $reason ) ] ) : '';
    }

    /**
     * Formats minor units, or an empty string without a currency.
     *
     * @since 1.0.0
     *
     * @param  mixed   $amount    Minor units.
     * @param  string  $currency  Currency.
     *
     * @return string
     */
    protected function money( mixed $amount, string $currency ): string
    {
        if ( ! is_numeric( $amount ) ) {
            return '—';
        }

        if ( 3 !== strlen( $currency ) ) {
            return (string) (int) $amount;
        }

        try {
            return MoneyFormatter::format( (int) $amount, strtoupper( $currency ) );
        } catch ( Throwable ) {
            return (int) $amount . ' ' . strtoupper( $currency );
        }
    }

    /**
     * `: amount` when there is one.
     *
     * @since 1.0.0
     *
     * @param  mixed   $amount    Minor units.
     * @param  string  $currency  Currency.
     *
     * @return string
     */
    protected function amountSuffix( mixed $amount, string $currency ): string
    {
        return is_numeric( $amount ) ? ' — ' . $this->money( $amount, $currency ) : '';
    }

    /**
     * A system status label.
     *
     * @since 1.0.0
     *
     * @param  mixed  $status  Status value.
     *
     * @return string
     */
    protected function statusLabel( mixed $status ): string
    {
        return is_string( $status ) && '' !== $status ? StatusPresenter::present( 'system', $status )['label'] : '—';
    }

    /**
     * A sub-status change.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload  Payload.
     *
     * @return string
     */
    protected function describeSubstatus( array $payload ): string
    {
        $to   = $this->substatusLabel( $payload['to_id'] ?? null, $payload['to_key'] ?? null );
        $from = $this->substatusLabel( $payload['from_id'] ?? null, $payload['from_key'] ?? null );

        $text = null === $from
            ? __( 'Sub-status set to ":to"', [ 'to' => $to ?? '—' ] )
            : __( 'Sub-status changed from ":from" to ":to"', [ 'from' => $from, 'to' => $to ?? '—' ] );

        if ( isset( $payload['board_id'] ) && null !== $payload['board_id'] ) {
            $text .= ' ' . __( 'on board ":board"', [ 'board' => $this->boardName( $payload ) ] );
        }

        return $text;
    }

    /**
     * A sub-status label by id, falling back to its key.
     *
     * @since 1.0.0
     *
     * @param  mixed  $id   Sub-status id.
     * @param  mixed  $key  Sub-status key.
     *
     * @return string|null
     */
    protected function substatusLabel( mixed $id, mixed $key ): ?string
    {
        if ( is_numeric( $id ) && isset( $this->lookups['substatuses'][ (int) $id ] ) ) {
            return $this->lookups['substatuses'][ (int) $id ];
        }

        return is_string( $key ) && '' !== $key ? $key : null;
    }

    /**
     * A board's name, falling back to its key.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload  Payload.
     *
     * @return string
     */
    protected function boardName( array $payload ): string
    {
        $id = $payload['board_id'] ?? null;

        if ( is_numeric( $id ) && isset( $this->lookups['boards'][ (int) $id ] ) ) {
            return $this->lookups['boards'][ (int) $id ];
        }

        $key = $payload['board_key'] ?? null;

        return is_string( $key ) && '' !== $key ? $key : __( 'Deleted board' );
    }

    /**
     * A cancellation.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload   Payload.
     * @param  string                $currency  Order currency.
     *
     * @return string
     */
    protected function describeCancel( array $payload, string $currency ): string
    {
        $parts = [ __( 'Order cancelled' ) . $this->withReason( $payload ) ];
        $units = array_sum( array_map( static fn ( mixed $line ): int => is_array( $line ) ? (int) ( $line['quantity'] ?? 0 ) : 0, (array) ( $payload['released'] ?? [] ) ) );

        if ( $units > 0 ) {
            $parts[] = trans_choice( ':count reserved unit released|:count reserved units released', $units, [ 'count' => $units ] );
        }

        if ( true === ( $payload['payment_voided'] ?? false ) ) {
            $parts[] = __( 'payment voided' );
        }

        if ( is_numeric( $payload['refund_owed'] ?? null ) && (int) $payload['refund_owed'] > 0 ) {
            $parts[] = __( ':amount still to refund', [ 'amount' => $this->money( $payload['refund_owed'], $currency ) ] );
        }

        return implode( '; ', $parts );
    }

    /**
     * An order edit.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload   Payload.
     * @param  string                $currency  Order currency.
     *
     * @return string
     */
    protected function describeEdit( array $payload, string $currency ): string
    {
        $before = $payload['totals']['before']['total_amount'] ?? null;
        $after  = $payload['totals']['after']['total_amount'] ?? null;
        $text   = __( 'Order edited' );

        if ( is_numeric( $before ) && is_numeric( $after ) && (int) $before !== (int) $after ) {
            $currency = (string) ( $payload['totals']['after']['currency'] ?? $currency );
            $text .= ' — ' . __( 'total :before → :after', [ 'before' => $this->money( $before, $currency ), 'after' => $this->money( $after, $currency ) ] );
        }

        return $text . $this->withReason( $payload );
    }

    /**
     * A note added or deleted.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload  Payload.
     * @param  bool                  $added    Added (else deleted).
     *
     * @return string
     */
    protected function describeNote( array $payload, bool $added ): string
    {
        $excerpt = is_string( $payload['excerpt'] ?? null ) ? (string) $payload['excerpt'] : '';

        if ( ! $added ) {
            return '' === $excerpt ? __( 'Note deleted' ) : __( 'Note deleted: ":excerpt"', [ 'excerpt' => $excerpt ] );
        }

        $text = '' === $excerpt ? __( 'Note added' ) : __( 'Note added: ":excerpt"', [ 'excerpt' => $excerpt ] );

        return true === ( $payload['is_customer_visible'] ?? false ) ? $text . ' ' . __( '(visible to the customer)' ) : $text;
    }

    /**
     * An update, listing the changed fields.
     *
     * @since 1.0.0
     *
     * @param  string                $prefix   What changed.
     * @param  array<string, mixed>  $payload  Payload with `changes`.
     *
     * @return string
     */
    protected function describeChanges( string $prefix, array $payload ): string
    {
        $fields = array_map(
            static fn ( int|string $field ): string => str_replace( '_', ' ', (string) $field ),
            array_keys( (array) ( $payload['changes'] ?? [] ) ),
        );

        return [] === $fields ? $prefix : __( ':what: :fields', [ 'what' => $prefix, 'fields' => implode( ', ', $fields ) ] );
    }

    /**
     * A prefix plus the changed field names from a `fields` list payload.
     *
     * @since 1.0.0
     *
     * @param  string                $prefix   What changed.
     * @param  array<string, mixed>  $payload  Payload.
     *
     * @return string
     */
    protected function describeFields( string $prefix, array $payload ): string
    {
        $fields = array_map(
            static fn ( mixed $field ): string => str_replace( '_', ' ', (string) $field ),
            array_filter( (array) ( $payload['fields'] ?? [] ), 'is_scalar' ),
        );

        return [] === $fields ? $prefix : __( ':what: :fields', [ 'what' => $prefix, 'fields' => implode( ', ', $fields ) ] );
    }

    /**
     * A variant's label: its SKU, name, or id.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload  Payload.
     *
     * @return string
     */
    protected function variantLabel( array $payload ): string
    {
        foreach ( [ 'sku', 'name' ] as $key ) {
            if ( is_string( $payload[ $key ] ?? null ) && '' !== $payload[ $key ] ) {
                return (string) $payload[ $key ];
            }
        }

        return '#' . (int) ( $payload['variant_id'] ?? 0 );
    }

    /**
     * A stock adjustment.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $payload  Payload.
     *
     * @return string
     */
    protected function describeInventory( array $payload ): string
    {
        $delta = (int) ( $payload['delta'] ?? 0 );
        $after = $payload['quantity_on_hand']['after'] ?? null;

        $text = $delta >= 0
            ? trans_choice( 'Stock increased by :count|Stock increased by :count', $delta, [ 'count' => $delta ] )
            : trans_choice( 'Stock decreased by :count|Stock decreased by :count', abs( $delta ), [ 'count' => abs( $delta ) ] );

        if ( is_numeric( $after ) ) {
            $text .= ' ' . __( '(now :count on hand)', [ 'count' => (int) $after ] );
        }

        return $text;
    }
}
