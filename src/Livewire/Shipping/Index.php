<?php

/**
 * Shipping zones and methods screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Shipping;

use ArtisanPackUI\Ecommerce\Models\CustomerAddress;
use ArtisanPackUI\Ecommerce\Models\ShippingMethod;
use ArtisanPackUI\Ecommerce\Models\ShippingZone;
use ArtisanPackUI\Ecommerce\Models\TaxClass;
use ArtisanPackUI\Ecommerce\Models\TaxRate;
use ArtisanPackUI\Ecommerce\Registries\ShippingMethodTypeRegistry;
use ArtisanPackUI\Ecommerce\Registries\ShippingRateProviderRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithActionToken;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\WithConfigForms;
use ArtisanPackUI\EcommerceAdminLivewire\Registries\ConfigFormRegistry;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceAdminLivewire\Support\Countries;
use ArtisanPackUI\EcommerceAdminLivewire\Support\ShippingMethods;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The shipping screen (spec §7.6): zones ordered by priority, each with its
 * methods.
 *
 * - Zones: name, countries, regions, postal patterns, priority, active.
 *   Created and edited in a drawer; deleting a zone deletes its methods and
 *   carries a one-time action token.
 * - Methods: a type from `ShippingMethodTypeRegistry` (or a live-rate
 *   provider from `ShippingRateProviderRegistry`, stored as `provider:{key}`),
 *   label, tax class, active, and position. The type's settings render
 *   through the config-form renderer (spec §8.4). Methods reorder within
 *   their zone with "move up" / "move down", so it works by keyboard.
 * - Coverage warning: the screen lists countries the store has reason to
 *   ship to but no active zone covers. The engine has no "selling countries"
 *   setting, so "has reason to ship to" means a country that appears in an
 *   inactive zone, an active tax rate, or a saved customer address. A
 *   country listed by an active zone without region or postal limits is
 *   covered; one listed only by limited zones is "partly covered".
 *
 * The engine has no shipping write service, so the screen writes the models
 * directly under the `shippingZone` abilities, with the same rules as the
 * engine's REST requests.
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
    use WithConfigForms;

    /**
     * Cache key for {@see self::storeCountries()}.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const COUNTRIES_CACHE_KEY = 'ecommerce-admin.shipping.store-countries';

    /**
     * Seconds {@see self::storeCountries()} is cached.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const COUNTRIES_TTL = 300;

    /**
     * Zones whose methods are shown.
     *
     * @since 1.0.0
     *
     * @var array<int, int>
     */
    public array $expanded = [];

    /**
     * Whether the zone drawer is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $editingZone = false;

    /**
     * The zone being edited, or null when creating.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $zoneId = null;

    /**
     * The zone form.
     *
     * @since 1.0.0
     *
     * @var array{name: string, country_codes: array<int, string>, region_codes: string, postal_patterns: string, priority: int|string|null, is_active: bool}
     */
    public array $zoneForm = [
        'name'            => '',
        'country_codes'   => [],
        'region_codes'    => '',
        'postal_patterns' => '',
        'priority'        => 0,
        'is_active'       => true,
    ];

    /**
     * Whether the method drawer is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $editingMethod = false;

    /**
     * The zone a new method is added to.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $methodZoneId = null;

    /**
     * The method being edited, or null when creating.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $methodId = null;

    /**
     * The method form. `config` is the config-form state: an array, or JSON
     * text for a type without a schema.
     *
     * @since 1.0.0
     *
     * @var array{key: string, label: string, tax_class_key: string|null, is_active: bool, config: array<string, mixed>|string}
     */
    public array $methodForm = [
        'key'           => '',
        'label'         => '',
        'tax_class_key' => null,
        'is_active'     => true,
        'config'        => [],
    ];

    /**
     * What is waiting for delete confirmation: `zone` or `method`.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $deletingType = null;

    /**
     * The id waiting for delete confirmation.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $deletingId = null;

    /**
     * Whether the delete confirmation is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $confirmingDelete = false;

    /**
     * Authorizes the screen.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function mount(): void
    {
        $this->authorizeEcommerce( 'viewAny', ShippingZone::class );
    }

    /**
     * Re-authorizes the screen on every update request.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeEcommerce( 'viewAny', ShippingZone::class );
    }

    /**
     * Shows or hides a zone's methods.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Zone id.
     *
     * @return void
     */
    public function toggleZone( int $id ): void
    {
        $this->expanded = in_array( $id, $this->expanded, true )
            ? array_values( array_diff( $this->expanded, [ $id ] ) )
            : [ ...$this->expanded, $id ];
    }

    /**
     * Opens the drawer for a new zone.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function createZone(): void
    {
        $this->authorizeEcommerce( 'create', ShippingZone::class );

        $this->resetErrorBag();
        $this->zoneId      = null;
        $this->zoneForm    = [
            'name'            => '',
            'country_codes'   => [],
            'region_codes'    => '',
            'postal_patterns' => '',
            'priority'        => (int) ShippingZone::query()->max( 'priority' ) + 1,
            'is_active'       => true,
        ];
        $this->editingZone = true;
    }

    /**
     * Opens the drawer for an existing zone.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Zone id.
     *
     * @return void
     */
    public function editZone( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'shippingZone.update' );

        $zone = ShippingZone::query()->find( $id );

        if ( null === $zone ) {
            return;
        }

        $this->resetErrorBag();
        $this->zoneId      = (int) $zone->id;
        $this->zoneForm    = [
            'name'            => (string) $zone->name,
            'country_codes'   => array_values( array_map( 'strval', (array) $zone->country_codes ) ),
            'region_codes'    => implode( ', ', (array) ( $zone->region_codes ?? [] ) ),
            'postal_patterns' => implode( "\n", (array) ( $zone->postal_patterns ?? [] ) ),
            'priority'        => (int) $zone->priority,
            'is_active'       => (bool) $zone->is_active,
        ];
        $this->editingZone = true;
    }

    /**
     * Saves the zone drawer.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveZone(): void
    {
        $zone = null === $this->zoneId ? null : ShippingZone::query()->find( $this->zoneId );

        if ( null === $zone ) {
            $this->authorizeEcommerce( 'create', ShippingZone::class );

            if ( null !== $this->zoneId ) {
                // Deleted elsewhere while the drawer was open: don't recreate it.
                $this->editingZone = false;
                $this->zoneId      = null;
                $this->toastWarning( __( 'This shipping zone was deleted.' ), __( 'Someone deleted it while you were editing, so your changes were not saved.' ) );

                return;
            }
        } else {
            $this->authorizeEcommerceAbility( 'shippingZone.update' );
        }

        $this->zoneForm['country_codes'] = array_values( array_unique( array_map(
            static fn ( mixed $code ): string => strtoupper( trim( (string) $code ) ),
            array_filter( (array) $this->zoneForm['country_codes'], static fn ( mixed $code ): bool => is_scalar( $code ) && '' !== trim( (string) $code ) ),
        ) ) );

        $regions  = self::splitList( (string) $this->zoneForm['region_codes'], true );
        $patterns = self::splitList( (string) $this->zoneForm['postal_patterns'], false );

        $this->validate(
            [
                'zoneForm.name'            => [ 'required', 'string', 'max:255' ],
                'zoneForm.country_codes'   => [ 'required', 'array', 'min:1' ],
                'zoneForm.country_codes.*' => [ 'string', 'size:2', Rule::in( Countries::CODES ) ],
                'zoneForm.priority'        => [ 'required', 'integer' ],
                'zoneForm.is_active'       => [ 'boolean' ],
            ],
            [],
            [
                'zoneForm.name'            => __( 'name' ),
                'zoneForm.country_codes'   => __( 'countries' ),
                'zoneForm.country_codes.*' => __( 'country' ),
                'zoneForm.priority'        => __( 'priority' ),
            ],
        );

        foreach ( [ 'region_codes' => [ $regions, 10 ], 'postal_patterns' => [ $patterns, 60 ] ] as $field => [ $values, $max ] ) {
            foreach ( $values as $value ) {
                if ( mb_strlen( $value ) > $max ) {
                    $this->addError( 'zoneForm.' . $field, __( 'Each entry may be at most :max characters.', [ 'max' => $max ] ) );

                    return;
                }
            }
        }

        $data = [
            'name'            => trim( (string) $this->zoneForm['name'] ),
            'country_codes'   => $this->zoneForm['country_codes'],
            'region_codes'    => [] === $regions ? null : $regions,
            'postal_patterns' => [] === $patterns ? null : $patterns,
            'priority'        => (int) $this->zoneForm['priority'],
            'is_active'       => (bool) $this->zoneForm['is_active'],
        ];

        if ( null === $zone ) {
            $zone             = ShippingZone::query()->create( $data );
            $this->expanded[] = (int) $zone->id;
        } else {
            $zone->fill( $data )->save();
        }

        $this->editingZone = false;
        $this->toastSuccess( __( 'Shipping zone ":name" saved.', [ 'name' => $zone->name ] ) );
    }

    /**
     * Opens the drawer for a new method in a zone.
     *
     * @since 1.0.0
     *
     * @param  int  $zoneId  Zone id.
     *
     * @return void
     */
    public function createMethod( int $zoneId ): void
    {
        $this->authorizeEcommerceAbility( 'shippingZone.update' );

        if ( ! ShippingZone::query()->whereKey( $zoneId )->exists() ) {
            return;
        }

        $key = (string) ( self::methodTypeOptions()[0]['id'] ?? '' );

        $this->resetErrorBag();
        $this->methodId      = null;
        $this->methodZoneId  = $zoneId;
        $this->methodForm    = [
            'key'           => $key,
            'label'         => '',
            'tax_class_key' => null,
            'is_active'     => true,
            'config'        => $this->methodConfigState( $key, [] ),
        ];
        $this->editingMethod = true;
    }

    /**
     * Opens the drawer for an existing method.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Method id.
     *
     * @return void
     */
    public function editMethod( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'shippingZone.update' );

        $method = ShippingMethod::query()->find( $id );

        if ( null === $method ) {
            return;
        }

        $this->resetErrorBag();
        $this->methodId      = (int) $method->id;
        $this->methodZoneId  = (int) $method->zone_id;
        $this->methodForm    = [
            'key'           => (string) $method->key,
            'label'         => (string) $method->label,
            'tax_class_key' => $method->tax_class_key,
            'is_active'     => (bool) $method->is_active,
            'config'        => $this->methodConfigState( (string) $method->key, (array) $method->config ),
        ];
        $this->editingMethod = true;
    }

    /**
     * Resets the method settings when its type changes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedMethodFormKey(): void
    {
        $this->resetErrorBag( 'methodForm.config' );
        $this->methodForm['config'] = $this->methodConfigState( (string) $this->methodForm['key'], [] );
    }

    /**
     * Saves the method drawer.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function saveMethod(): void
    {
        $this->authorizeEcommerceAbility( 'shippingZone.update' );

        $zone   = null === $this->methodZoneId ? null : ShippingZone::query()->find( $this->methodZoneId );
        $method = null === $this->methodId ? null : ShippingMethod::query()->find( $this->methodId );

        if ( null === $zone || ( null !== $this->methodId && null === $method ) ) {
            $this->editingMethod = false;

            if ( null !== $this->methodZoneId ) {
                $this->toastWarning(
                    null === $zone ? __( 'This shipping zone was deleted.' ) : __( 'This shipping method was deleted.' ),
                    __( 'Someone deleted it while you were editing, so your changes were not saved.' ),
                );
            }

            return;
        }

        $this->methodForm['tax_class_key'] = '' === ( $this->methodForm['tax_class_key'] ?? '' ) ? null : (string) $this->methodForm['tax_class_key'];

        $this->validate(
            [
                'methodForm.key'           => [ 'required', 'string', 'max:120', function ( string $attribute, mixed $value, Closure $fail ): void {
                    if ( ! is_string( $value ) || ! self::isKnownMethodKey( $value ) ) {
                        $fail( __( 'Choose a shipping method type.' ) );
                    }
                } ],
                'methodForm.label'         => [ 'required', 'string', 'max:255' ],
                'methodForm.tax_class_key' => [ 'nullable', 'string', Rule::exists( TaxClass::class, 'key' ) ],
                'methodForm.is_active'     => [ 'boolean' ],
            ],
            [],
            [
                'methodForm.key'           => __( 'type' ),
                'methodForm.label'         => __( 'label' ),
                'methodForm.tax_class_key' => __( 'tax class' ),
            ],
        );

        $key = (string) $this->methodForm['key'];

        if ( ! app( ConfigFormRegistry::class )->has( 'shipping-method', $key ) ) {
            $decoded = json_decode( (string) $this->methodForm['config'], true );

            if ( ! is_array( $decoded ) || array_is_list( $decoded ) && [] !== $decoded ) {
                $this->addError( 'methodForm.config', __( 'The settings must be a JSON object.' ) );

                return;
            }
        }

        $config = $this->validateConfigForm( 'shipping-method', $key, 'methodForm.config' );

        $data = [
            'key'           => $key,
            'label'         => trim( (string) $this->methodForm['label'] ),
            'tax_class_key' => $this->methodForm['tax_class_key'],
            'is_active'     => (bool) $this->methodForm['is_active'],
            'config'        => $config,
        ];

        if ( null === $method ) {
            $data['position'] = (int) ShippingMethod::query()->where( 'zone_id', $zone->id )->max( 'position' ) + 1;
            $method           = $zone->methods()->create( $data );
        } else {
            $method->fill( $data )->save();
        }

        if ( ! in_array( (int) $zone->id, $this->expanded, true ) ) {
            $this->expanded[] = (int) $zone->id;
        }

        $this->editingMethod = false;
        $this->toastSuccess( __( 'Shipping method ":name" saved.', [ 'name' => $method->label ] ) );
    }

    /**
     * Moves a method one place up or down within its zone.
     *
     * @since 1.0.0
     *
     * @param  int  $id      Method id.
     * @param  int  $offset  -1 for up, 1 for down.
     *
     * @return void
     */
    public function moveMethod( int $id, int $offset ): void
    {
        $this->authorizeEcommerceAbility( 'shippingZone.update' );

        $method = ShippingMethod::query()->find( $id );

        if ( null === $method || ! in_array( $offset, [ -1, 1 ], true ) ) {
            return;
        }

        $siblings = ShippingMethod::query()
            ->where( 'zone_id', $method->zone_id )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->pluck( 'id' )
            ->map( static fn ( mixed $sibling ): int => (int) $sibling )
            ->all();

        $from = array_search( (int) $method->id, $siblings, true );
        $to   = false === $from ? false : $from + $offset;

        if ( false === $to || $to < 0 || $to >= count( $siblings ) ) {
            return;
        }

        [ $siblings[ $from ], $siblings[ $to ] ] = [ $siblings[ $to ], $siblings[ $from ] ];

        DB::transaction( static function () use ( $siblings ): void {
            foreach ( $siblings as $position => $siblingId ) {
                ShippingMethod::query()->whereKey( $siblingId )->update( [ 'position' => $position ] );
            }
        } );
    }

    /**
     * Asks to confirm deleting a zone (with its methods).
     *
     * @since 1.0.0
     *
     * @param  int  $id  Zone id.
     *
     * @return void
     */
    public function confirmDeleteZone( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'shippingZone.delete' );

        $this->openDeleteConfirmation( 'zone', ShippingZone::query()->whereKey( $id )->exists() ? $id : null );
    }

    /**
     * Asks to confirm deleting a method.
     *
     * @since 1.0.0
     *
     * @param  int  $id  Method id.
     *
     * @return void
     */
    public function confirmDeleteMethod( int $id ): void
    {
        $this->authorizeEcommerceAbility( 'shippingZone.update' );

        $this->openDeleteConfirmation( 'method', ShippingMethod::query()->whereKey( $id )->exists() ? $id : null );
    }

    /**
     * Dismisses the delete confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelDelete(): void
    {
        $this->deletingType     = null;
        $this->deletingId       = null;
        $this->confirmingDelete = false;
    }

    /**
     * Deletes the zone or method waiting for confirmation, once per token.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The action token minted with the confirmation.
     *
     * @return void
     */
    public function delete( string $token ): void
    {
        if ( 'method' === $this->deletingType ) {
            $this->authorizeEcommerceAbility( 'shippingZone.update' );
            $subject = null === $this->deletingId ? null : ShippingMethod::query()->find( $this->deletingId );
        } else {
            $this->authorizeEcommerceAbility( 'shippingZone.delete' );
            $subject = null === $this->deletingId || 'zone' !== $this->deletingType ? null : ShippingZone::query()->find( $this->deletingId );
        }

        if ( null === $subject || ! $this->confirmingDelete ) {
            $this->cancelDelete();

            return;
        }

        $type = (string) $this->deletingType;
        $name = $subject instanceof ShippingZone ? (string) $subject->name : (string) $subject->label;

        $deleted = $this->withActionToken( $token, 'delete-' . $type, static function () use ( $subject ): bool {
            if ( $subject instanceof ShippingZone ) {
                ShippingMethod::query()->where( 'zone_id', $subject->id )->delete();
            }

            $subject->delete();

            return true;
        }, $subject );

        $this->cancelDelete();

        if ( true === $deleted ) {
            $this->toastSuccess( 'zone' === $type
                ? __( 'Shipping zone ":name" deleted.', [ 'name' => $name ] )
                : __( 'Shipping method ":name" deleted.', [ 'name' => $name ] ) );
        }
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $zones = ShippingZone::query()
            ->with( 'methods' )
            ->orderBy( 'priority' )
            ->orderBy( 'id' )
            ->get();

        $deleting = null;

        if ( $this->confirmingDelete && null !== $this->deletingId ) {
            $deleting = 'zone' === $this->deletingType
                ? $zones->firstWhere( 'id', $this->deletingId )
                : ShippingMethod::query()->find( $this->deletingId );
        }

        $user = auth()->user();

        return view( 'ecommerce-admin::livewire.shipping.index', [
            'zones'          => $zones,
            'coverage'       => self::coverage( $zones ),
            'canCreate'      => $this->canEcommerce( 'create', ShippingZone::class ),
            'canUpdate'      => Authorization::allows( $user, 'shippingZone.update' ),
            'canDelete'      => Authorization::allows( $user, 'shippingZone.delete' ),
            'countryOptions' => Countries::options(),
            'typeOptions'    => self::methodTypeOptions( $this->editingMethod ? (string) $this->methodForm['key'] : null ),
            'taxClasses'     => self::taxClassOptions(),
            'taxClassLabels' => TaxClass::query()->pluck( 'label', 'key' )->all(),
            'deleting'       => $deleting,
            'deleteToken'    => null === $deleting ? null : $this->actionToken( 'delete-' . $this->deletingType, $deleting ),
        ] );
    }

    /**
     * A method type's display name.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Method key (`provider:{key}` for a rate provider).
     *
     * @return string
     */
    public static function methodTypeLabel( string $key ): string
    {
        foreach ( self::methodTypeOptions() as $option ) {
            if ( $key === $option['id'] ) {
                return $option['name'];
            }
        }

        return ShippingMethods::label( $key );
    }

    /**
     * Which countries lack an active zone.
     *
     * @since 1.0.0
     *
     * @param  Collection<int, ShippingZone>  $zones  Every zone.
     *
     * @return array{active: int, uncovered: array<int, string>, partial: array<int, string>}
     */
    public static function coverage( Collection $zones ): array
    {
        $full    = [];
        $partial = [];
        $wanted  = [];

        foreach ( $zones as $zone ) {
            $countries = array_map( static fn ( mixed $code ): string => strtoupper( (string) $code ), (array) $zone->country_codes );

            if ( ! $zone->is_active ) {
                array_push( $wanted, ...$countries );

                continue;
            }

            $limited = [] !== array_filter( (array) ( $zone->region_codes ?? [] ) ) || [] !== array_filter( (array) ( $zone->postal_patterns ?? [] ) );

            foreach ( $countries as $code ) {
                if ( $limited ) {
                    $partial[ $code ] = true;
                } else {
                    $full[ $code ] = true;
                }
            }
        }

        array_push( $wanted, ...self::storeCountries() );

        $wanted = array_unique( array_filter( array_map( static fn ( mixed $code ): string => strtoupper( trim( (string) $code ) ), $wanted ) ) );
        sort( $wanted );

        $uncovered = [];
        $partly    = [];

        foreach ( $wanted as $code ) {
            if ( isset( $full[ $code ] ) ) {
                continue;
            }

            if ( isset( $partial[ $code ] ) ) {
                $partly[] = $code;
            } else {
                $uncovered[] = $code;
            }
        }

        return [
            'active'    => $zones->where( 'is_active', true )->count(),
            'uncovered' => $uncovered,
            'partial'   => $partly,
        ];
    }

    /**
     * Countries the store taxes or ships to: those of active tax rates and
     * customer addresses. Cached for {@see self::COUNTRIES_TTL} seconds, since
     * scanning the address table on every request is slow on large stores.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public static function storeCountries(): array
    {
        return (array) Cache::remember( self::COUNTRIES_CACHE_KEY, self::COUNTRIES_TTL, static fn (): array => array_values( array_unique( array_merge(
            TaxRate::query()->where( 'is_active', true )->distinct()->pluck( 'country_code' )->all(),
            CustomerAddress::query()->whereNotNull( 'country_code' )->distinct()->pluck( 'country_code' )->all(),
        ) ) ) );
    }

    /**
     * The method types to choose from: registered types, then live-rate
     * providers as `provider:{key}`, then `$current` when it is neither.
     *
     * @since 1.0.0
     *
     * @param  string|null  $current  The method's current key.
     *
     * @return array<int, array{id: string, name: string}>
     */
    private static function methodTypeOptions( ?string $current = null ): array
    {
        $options = [];

        foreach ( ShippingMethods::options() as $option ) {
            $options[ $option['id'] ] = $option;
        }

        try {
            foreach ( app( ShippingRateProviderRegistry::class )->all() as $key => $provider ) {
                $id             = ShippingMethod::PROVIDER_PREFIX . $key;
                $options[ $id ] = [
                    'id'   => $id,
                    'name' => __( 'Live rates: :provider', [ 'provider' => method_exists( $provider, 'label' ) ? (string) $provider->label() : Str::headline( (string) $key ) ] ),
                ];
            }
        } catch ( Throwable ) {
            // No providers; offer the method types only.
        }

        if ( null !== $current && '' !== $current && ! isset( $options[ $current ] ) ) {
            $options[ $current ] = [ 'id' => $current, 'name' => Str::headline( $current ) ];
        }

        return array_values( $options );
    }

    /**
     * Whether `$key` names a registered method type or rate provider.
     *
     * @since 1.0.0
     *
     * @param  string  $key  Method key.
     *
     * @return bool
     */
    private static function isKnownMethodKey( string $key ): bool
    {
        if ( str_starts_with( $key, ShippingMethod::PROVIDER_PREFIX ) ) {
            return app( ShippingRateProviderRegistry::class )->has( substr( $key, strlen( ShippingMethod::PROVIDER_PREFIX ) ) );
        }

        return app( ShippingMethodTypeRegistry::class )->has( $key );
    }

    /**
     * The tax class choices.
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: string, name: string}>
     */
    private static function taxClassOptions(): array
    {
        return TaxClass::query()
            ->orderBy( 'label' )
            ->get( [ 'key', 'label' ] )
            ->map( static fn ( TaxClass $class ): array => [ 'id' => (string) $class->key, 'name' => $class->label . ' (' . $class->key . ')' ] )
            ->all();
    }

    /**
     * Splits comma- or line-separated text into trimmed, unique entries.
     *
     * @since 1.0.0
     *
     * @param  string  $text       Input.
     * @param  bool    $uppercase  Uppercase each entry.
     *
     * @return array<int, string>
     */
    private static function splitList( string $text, bool $uppercase ): array
    {
        $values = array_map( 'trim', preg_split( '/[\r\n,]+/', $text ) ?: [] );
        $values = array_values( array_unique( array_filter( $values, static fn ( string $value ): bool => '' !== $value ) ) );

        return $uppercase ? array_values( array_unique( array_map( 'strtoupper', $values ) ) ) : $values;
    }

    /**
     * The config-form state for a method type.
     *
     * @since 1.0.0
     *
     * @param  string                $key     Method key.
     * @param  array<string, mixed>  $config  Stored config.
     *
     * @return array<string, mixed>|string
     */
    private function methodConfigState( string $key, array $config ): array|string
    {
        return '' === $key ? [] : $this->configFormState( 'shipping-method', $key, $config );
    }

    /**
     * Opens the delete confirmation.
     *
     * @since 1.0.0
     *
     * @param  string    $type  `zone` or `method`.
     * @param  int|null  $id    Id, or null when it no longer exists.
     *
     * @return void
     */
    private function openDeleteConfirmation( string $type, ?int $id ): void
    {
        $this->deletingType     = null === $id ? null : $type;
        $this->deletingId       = $id;
        $this->confirmingDelete = null !== $id;
    }
}
