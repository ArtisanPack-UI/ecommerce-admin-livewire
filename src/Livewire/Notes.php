<?php

/**
 * Internal notes component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire;

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\CustomerNote;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderNote;
use ArtisanPackUI\Ecommerce\Services\CustomerNoteService;
use ArtisanPackUI\Ecommerce\Services\OrderNoteService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Support\UserNames;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Internal notes on an order or a customer (spec §8.3).
 *
 * Mounted with the subject model. Anyone who may view the subject sees its
 * notes; adding one needs the `update` ability on the subject, and deleting
 * one needs `update` on the subject. Order notes carry a
 * "visible to customer" toggle (off by default) that maps to
 * `ecommerce_order_notes.is_customer_visible`; customer notes are always internal.
 *
 * Bodies are stripped of markup on the way in and escaped on the way out.
 * Writes go through the engine's `OrderNoteService` / `CustomerNoteService`,
 * which also write the `note.added` / `note.deleted` timeline or activity
 * entries; the component then dispatches {@see self::NOTES_CHANGED_EVENT}
 * so the subject's timeline refreshes.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Notes extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;

    /**
     * Subject types the component accepts.
     *
     * @since 1.0.0
     *
     * @var array<int, class-string<Model>>
     */
    public const SUBJECTS = [ Order::class, Customer::class ];

    /**
     * Browser event dispatched after a note is added or deleted.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const NOTES_CHANGED_EVENT = 'ecommerce-admin-notes-changed';

    /**
     * Longest note body, in characters.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_LENGTH = 5000;

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
     * The note being written.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $body = '';

    /**
     * Whether the new order note is shown to the customer.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $isCustomerVisible = false;

    /**
     * The subject, loaded once per request.
     *
     * @since 1.0.0
     *
     * @var Model|null
     */
    private ?Model $loadedSubject = null;

    /**
     * Stores and authorizes the subject.
     *
     * @since 1.0.0
     *
     * @param  Model  $subject  An order or a customer.
     *
     * @throws InvalidArgumentException When the subject type is not supported.
     *
     * @return void
     */
    public function mount( Model $subject ): void
    {
        if ( ! in_array( $subject::class, self::SUBJECTS, true ) ) {
            throw new InvalidArgumentException( sprintf( 'Notes cannot be attached to a %s.', $subject::class ) );
        }

        $this->subjectType   = $subject::class;
        $this->subjectId     = (int) $subject->getKey();
        $this->loadedSubject = $subject;

        $this->authorizeEcommerce( 'view', $subject );
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
        $this->authorizeEcommerce( 'view', $this->subject() );
    }

    /**
     * Adds a note.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addNote(): void
    {
        $subject = $this->subject();

        $this->authorizeEcommerce( 'update', $subject );

        $this->body = sanitizeText( $this->body );

        $this->validate(
            [
                'body'              => [ 'required', 'string', 'max:' . self::MAX_LENGTH ],
                'isCustomerVisible' => [ 'boolean' ],
            ],
            [],
            [ 'body' => __( 'note' ) ],
        );

        try {
            $subject instanceof Order
                ? app( OrderNoteService::class )->add( $subject, $this->body, $this->actorId(), $this->isCustomerVisible )
                : app( CustomerNoteService::class )->add( $subject, $this->body, $this->actorId() );
        } catch ( InvalidArgumentException $exception ) {
            $this->addError( 'body', $exception->getMessage() );

            return;
        }

        $this->reset( 'body', 'isCustomerVisible' );
        $this->notesChanged();
        $this->toastSuccess( __( 'Note added.' ) );
    }

    /**
     * Deletes a note of this subject.
     *
     * @since 1.0.0
     *
     * @param  int  $noteId  Note id.
     *
     * @return void
     */
    public function deleteNote( int $noteId ): void
    {
        $subject = $this->subject();
        $note    = $this->notesQuery( $subject )->whereKey( $noteId )->first();

        if ( null === $note ) {
            $this->toastWarning( __( 'That note no longer exists.' ) );

            return;
        }

        if ( ! $this->canDelete( $note, $subject ) ) {
            $this->denyEcommerce();
        }

        $note instanceof OrderNote
            ? app( OrderNoteService::class )->delete( $note, $this->actorId() )
            : app( CustomerNoteService::class )->delete( $note, $this->actorId() );

        $this->notesChanged();
        $this->toastSuccess( __( 'Note deleted.' ) );
    }

    /**
     * Renders the notes, newest first.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $subject = $this->subject();
        $notes   = $this->notesQuery( $subject )->orderByDesc( 'created_at' )->orderByDesc( 'id' )->get();
        $authors = UserNames::for( $notes->pluck( 'author_user_id' )->all() );

        return view( 'ecommerce-admin::livewire.notes', [
            'isOrder' => $subject instanceof Order,
            'canAdd'  => $this->canEcommerce( 'update', $subject ),
            'notes'   => $notes->map( fn ( Model $note ): array => [
                'id'        => (int) $note->getKey(),
                'body'      => (string) $note->getAttribute( 'body' ),
                'author'    => UserNames::label( null === $note->getAttribute( 'author_user_id' ) ? null : (int) $note->getAttribute( 'author_user_id' ), $authors ),
                'time'      => $note->getAttribute( 'created_at' ),
                'visible'   => $note instanceof OrderNote && (bool) $note->is_customer_visible,
                'canDelete' => $this->canDelete( $note, $subject ),
            ] )->all(),
        ] );
    }

    /**
     * Whether the current user may delete `$note`: only with `update` on
     * the subject, so an author who lost that right can't delete their
     * notes either.
     *
     * @since 1.0.0
     *
     * @param  Model  $note     Order or customer note.
     * @param  Model  $subject  The subject.
     *
     * @return bool
     */
    protected function canDelete( Model $note, Model $subject ): bool
    {
        return $this->canEcommerce( 'update', $subject );
    }

    /**
     * The subject's notes query.
     *
     * @since 1.0.0
     *
     * @param  Model  $subject  The subject.
     *
     * @return \Illuminate\Database\Eloquent\Builder<CustomerNote|OrderNote>
     */
    protected function notesQuery( Model $subject ): \Illuminate\Database\Eloquent\Builder
    {
        return $subject instanceof Order
            ? OrderNote::query()->where( 'order_id', $subject->getKey() )
            : CustomerNote::query()->where( 'customer_id', $subject->getKey() );
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

        $class = $this->subjectType;

        return $this->loadedSubject = $class::query()->findOrFail( $this->subjectId );
    }

    /**
     * Tells the timeline (and anything else listening) that the notes changed.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function notesChanged(): void
    {
        $this->dispatch( self::NOTES_CHANGED_EVENT, subjectType: $this->subjectType, subjectId: $this->subjectId );
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
}
