<?php

/**
 * Promotion form.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions;

use ArtisanPackUI\Ecommerce\Models\Promotion;
use ArtisanPackUI\Ecommerce\Models\PromotionAction;
use ArtisanPackUI\Ecommerce\Models\PromotionCondition;
use ArtisanPackUI\Ecommerce\Registries\PromotionSourceRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithConfigForms;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithPickers;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithRuleBuilder;
use ArtisanPackUI\EcommerceAdminLivewire\Queries\PromotionsQuery;
use ArtisanPackUI\EcommerceAdminLivewire\Support\AdminNav;
use ArtisanPackUI\EcommerceAdminLivewire\Support\UnsavedChanges;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Creates and edits a promotion (spec §7.5).
 *
 * Tabs: Details (name, key, description, source type, switch, priority,
 * exclusivity, start and end, usage limits), Rules (the conditions and
 * actions rule builder), and, once saved, Coupons (for coupon promotions),
 * Usage, and Activity.
 *
 * The promotion and its rules are saved together in one transaction. The
 * engine evaluates conditions and actions in id order, so saving replaces
 * the rows in the order shown.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Form extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;
    use WithConfigForms;
    use WithPickers;
    use WithRuleBuilder;

    /**
     * The tabs, in order.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const TABS = [ 'details', 'rules', 'coupons', 'usage', 'activity' ];

    /**
     * The `datetime-local` format the window fields use.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const DATETIME_FORMAT = 'Y-m-d\TH:i';

    /**
     * The promotion id; null while creating.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $promotionId = null;

    /**
     * Whether the user may only look.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    #[Locked]
    public bool $readOnly = false;

    /**
     * The open tab. Kept in the URL (`?tab=coupons`) so links can open a tab.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Url( except: 'details' )]
    public string $tab = 'details';

    /**
     * Name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $name = '';

    /**
     * Unique key (kebab-case).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $key = '';

    /**
     * Whether the key was typed, so it stops following the name.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $keyEdited = false;

    /**
     * Internal description.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $description = '';

    /**
     * Source type key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $sourceType = Promotion::SOURCE_AUTOMATIC;

    /**
     * Whether the promotion is switched on.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $isActive = true;

    /**
     * Priority; lower numbers apply first.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $priority = 0;

    /**
     * Whether the promotion refuses to combine with others.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $isExclusive = false;

    /**
     * Start, as `Y-m-d\TH:i`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $startsAt = '';

    /**
     * End, as `Y-m-d\TH:i`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $endsAt = '';

    /**
     * Total use limit.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $usageLimitTotal = null;

    /**
     * Per-customer use limit.
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $usageLimitPerCustomer = null;

    /**
     * The promotion, loaded once per request.
     *
     * @since 1.0.0
     *
     * @var Promotion|null
     */
    private ?Promotion $loadedPromotion = null;

    /**
     * Loads the promotion (or defaults) and authorizes.
     *
     * @since 1.0.0
     *
     * @param  int|Promotion|string|null  $promotion  Promotion or id; null to create.
     *
     * @return void
     */
    public function mount( Promotion|int|string|null $promotion = null ): void
    {
        if ( null === $promotion || '' === $promotion ) {
            $this->authorizeEcommerce( 'create', Promotion::class );
            $this->ruleBuilderState( [] );

            return;
        }

        $model = $promotion instanceof Promotion ? $promotion : Promotion::query()->findOrFail( (int) $promotion );

        $this->authorizeEcommerce( 'view', $model );

        $this->promotionId     = (int) $model->id;
        $this->loadedPromotion = $model;
        $this->readOnly        = ! $this->canEcommerce( 'update', $model );

        $this->fillFromPromotion( $model );
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
        $promotion = $this->promotion();

        null === $promotion
            ? $this->authorizeEcommerce( 'create', Promotion::class )
            : $this->authorizeEcommerce( 'view', $promotion );
    }

    /**
     * Fills the key from the name until the key is typed.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedName(): void
    {
        if ( ! $this->keyEdited && null === $this->promotionId ) {
            $this->key = Str::slug( $this->name );
        }
    }

    /**
     * Stops the key following the name once it is typed.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedKey(): void
    {
        $this->keyEdited = true;
    }

    /**
     * Validates and saves the promotion and its rules.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function save(): void
    {
        $promotion = $this->promotion();

        $this->authorizeWrite( $promotion );

        $this->name        = sanitizeText( $this->name );
        $this->description = sanitizeText( $this->description );
        $this->key         = Str::lower( trim( $this->key ) );

        try {
            $this->validate( $this->detailRules( $promotion ), $this->messages(), $this->validationAttributes() );
        } catch ( ValidationException $exception ) {
            $this->tab = 'details';

            throw $exception;
        }

        try {
            $rules = $this->validateRules();
        } catch ( ValidationException $exception ) {
            $this->tab = 'rules';

            throw $exception;
        }

        $creating = null === $promotion;

        $saved = DB::transaction( function () use ( $promotion, $rules ): Promotion {
            $model = $promotion ?? new Promotion();

            $model->fill( $this->promotionData() )->save();

            PromotionCondition::query()->where( 'promotion_id', $model->id )->delete();
            PromotionAction::query()->where( 'promotion_id', $model->id )->delete();

            foreach ( $rules['conditions'] as $row ) {
                PromotionCondition::query()->create( [ 'promotion_id' => $model->id, 'type' => $row['type'], 'config' => $row['config'] ] );
            }

            foreach ( $rules['actions'] as $row ) {
                PromotionAction::query()->create( [ 'promotion_id' => $model->id, 'type' => $row['type'], 'config' => $row['config'] ] );
            }

            return $model;
        } );

        $this->dispatch( UnsavedChanges::SAVED_EVENT );

        if ( $creating ) {
            $this->toastSuccess( __( 'Promotion created.' ) );

            $editRoute = AdminNav::ROUTE_PREFIX . 'promotions.edit';

            if ( Route::has( $editRoute ) ) {
                $this->redirectRoute( $editRoute, [ 'promotion' => $saved->id ] );
            }

            $this->promotionId     = (int) $saved->id;
            $this->loadedPromotion = $saved->fresh();
            $this->readOnly        = ! $this->canEcommerce( 'update', $this->loadedPromotion );
            $this->fillFromPromotion( $this->loadedPromotion );

            return;
        }

        $this->loadedPromotion = $saved->fresh();
        $this->fillFromPromotion( $this->loadedPromotion );

        $this->toastSuccess( __( 'Promotion saved.' ) );
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $promotion = $this->promotion();

        if ( ! in_array( $this->tab, $this->visibleTabs( $promotion ), true ) ) {
            $this->tab = 'details';
        }

        return view( 'ecommerce-admin::livewire.promotions.form', [
            'promotion'     => $promotion,
            'isCreate'      => null === $promotion,
            'state'         => null === $promotion ? null : PromotionsQuery::state( $promotion ),
            'tabs'          => $this->visibleTabs( $promotion ),
            'sourceOptions' => Index::sourceOptions(),
            'ruleBuilder'   => $this->ruleBuilderViewData(),
            'indexUrl'      => Route::has( AdminNav::ROUTE_PREFIX . 'promotions.index' ) ? route( AdminNav::ROUTE_PREFIX . 'promotions.index' ) : null,
            'timezone'      => (string) config( 'app.timezone', 'UTC' ),
        ] );
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, array{registry: string, label: string, add: string, empty: string}>
     */
    protected function ruleBuilderLists(): array
    {
        return [
            'conditions' => [
                'registry' => 'promotion-condition',
                'label'    => __( 'Conditions' ),
                'add'      => __( 'Add a condition' ),
                'empty'    => __( 'No conditions: every cart qualifies. Every condition you add must be met.' ),
            ],
            'actions'    => [
                'registry' => 'promotion-action',
                'label'    => __( 'Actions' ),
                'add'      => __( 'Add an action' ),
                'empty'    => __( 'No actions yet: the promotion gives nothing until you add one. Actions apply from top to bottom.' ),
            ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return void
     */
    protected function authorizeRuleBuilder(): void
    {
        $this->authorizeWrite( $this->promotion() );
    }

    /**
     * Authorizes creating or updating, and refuses read-only forms.
     *
     * @since 1.0.0
     *
     * @param  Promotion|null  $promotion  The promotion; null while creating.
     *
     * @return void
     */
    protected function authorizeWrite( ?Promotion $promotion ): void
    {
        null === $promotion
            ? $this->authorizeEcommerce( 'create', Promotion::class )
            : $this->authorizeEcommerce( 'update', $promotion );
    }

    /**
     * The details validation rules (mirroring the engine's REST request).
     *
     * @since 1.0.0
     *
     * @param  Promotion|null  $promotion  The promotion; null while creating.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function detailRules( ?Promotion $promotion ): array
    {
        $hasCoupons = null !== $promotion && $promotion->coupons()->exists();

        return [
            'name'                  => [ 'required', 'string', 'max:255' ],
            'key'                   => [ 'required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique( Promotion::class, 'key' )->ignore( $promotion?->id ) ],
            'description'           => [ 'nullable', 'string', 'max:5000' ],
            'sourceType'            => [ 'required', 'string', Rule::in( app( PromotionSourceRegistry::class )->keys() ), ...( $hasCoupons ? [ Rule::in( [ Promotion::SOURCE_COUPON ] ) ] : [] ) ],
            'isActive'              => [ 'boolean' ],
            'isExclusive'           => [ 'boolean' ],
            'priority'              => [ 'required', 'integer', 'between:-1000000,1000000' ],
            'startsAt'              => [ 'nullable', 'date_format:' . self::DATETIME_FORMAT ],
            'endsAt'                => array_values( array_filter( [ 'nullable', 'date_format:' . self::DATETIME_FORMAT, '' !== trim( $this->startsAt ) ? 'after:startsAt' : null ] ) ),
            'usageLimitTotal'       => [ 'nullable', 'integer', 'min:1', 'max:4294967295' ],
            'usageLimitPerCustomer' => [ 'nullable', 'integer', 'min:1', 'max:4294967295' ],
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'key.regex'     => __( 'Use lowercase letters, numbers, and single hyphens, like black-friday-2026.' ),
            'key.unique'    => __( 'Another promotion already uses this key.' ),
            'sourceType.in' => __( 'This promotion still has coupon codes. Delete them before changing the source.' ),
            'endsAt.after'  => __( 'The end must be after the start.' ),
        ];
    }

    /**
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name'                  => __( 'name' ),
            'key'                   => __( 'key' ),
            'description'           => __( 'description' ),
            'sourceType'            => __( 'source' ),
            'priority'              => __( 'priority' ),
            'startsAt'              => __( 'start' ),
            'endsAt'                => __( 'end' ),
            'usageLimitTotal'       => __( 'total use limit' ),
            'usageLimitPerCustomer' => __( 'per-customer use limit' ),
        ];
    }

    /**
     * The validated details as promotion attributes.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function promotionData(): array
    {
        $date  = static fn ( string $value ): ?Carbon => '' === trim( $value ) ? null : Carbon::createFromFormat( self::DATETIME_FORMAT, $value );
        $limit = static fn ( int|string|null $value ): ?int => null === $value || '' === $value ? null : (int) $value;

        return [
            'name'                     => trim( $this->name ),
            'key'                      => $this->key,
            'description'              => '' === trim( $this->description ) ? null : trim( $this->description ),
            'source_type'              => $this->sourceType,
            'is_active'                => $this->isActive,
            'is_exclusive'             => $this->isExclusive,
            'priority'                 => (int) $this->priority,
            'starts_at'                => $date( $this->startsAt ),
            'ends_at'                  => $date( $this->endsAt ),
            'usage_limit_total'        => $limit( $this->usageLimitTotal ),
            'usage_limit_per_customer' => $limit( $this->usageLimitPerCustomer ),
        ];
    }

    /**
     * Fills the form from a promotion.
     *
     * @since 1.0.0
     *
     * @param  Promotion  $promotion  The promotion.
     *
     * @return void
     */
    protected function fillFromPromotion( Promotion $promotion ): void
    {
        $this->name                  = (string) $promotion->name;
        $this->key                   = (string) $promotion->key;
        $this->keyEdited             = true;
        $this->description           = (string) $promotion->description;
        $this->sourceType            = (string) $promotion->source_type;
        $this->isActive              = (bool) $promotion->is_active;
        $this->isExclusive           = (bool) $promotion->is_exclusive;
        $this->priority              = (int) $promotion->priority;
        $this->startsAt              = $promotion->starts_at?->format( self::DATETIME_FORMAT ) ?? '';
        $this->endsAt                = $promotion->ends_at?->format( self::DATETIME_FORMAT ) ?? '';
        $this->usageLimitTotal       = $promotion->usage_limit_total;
        $this->usageLimitPerCustomer = $promotion->usage_limit_per_customer;

        $this->ruleBuilderState( [
            'conditions' => $promotion->conditions()->get( [ 'type', 'config' ] )->toArray(),
            'actions'    => $promotion->actions()->get( [ 'type', 'config' ] )->toArray(),
        ] );
    }

    /**
     * The tabs this promotion shows.
     *
     * @since 1.0.0
     *
     * @param  Promotion|null  $promotion  The promotion; null while creating.
     *
     * @return array<int, string>
     */
    protected function visibleTabs( ?Promotion $promotion ): array
    {
        if ( null === $promotion ) {
            return [ 'details', 'rules' ];
        }

        return array_values( array_filter(
            self::TABS,
            static fn ( string $tab ): bool => 'coupons' !== $tab || Promotion::SOURCE_COUPON === $promotion->source_type,
        ) );
    }

    /**
     * The promotion, or null while creating.
     *
     * @since 1.0.0
     *
     * @return Promotion|null
     */
    protected function promotion(): ?Promotion
    {
        if ( null === $this->promotionId ) {
            return null;
        }

        return $this->loadedPromotion ??= Promotion::query()->findOrFail( $this->promotionId );
    }
}
