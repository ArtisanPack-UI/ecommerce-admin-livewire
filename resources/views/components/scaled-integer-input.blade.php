{{--
    Money and percent inputs (#83). A design-system Input that takes a human
    amount and stores a scaled integer: the currency code in the prefix part,
    "%" in the suffix part. Parsing happens on blur or Enter; a value that
    can't be read shows a format error in the server error's place, so only
    one error shows at a time. See ScaledIntegerInput.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    $wireModel     = $attributes->wire( 'model' );
    $model         = (string) $wireModel->value();
    $live          = $wireModel->hasModifier( 'live' ) || $wireModel->hasModifier( 'blur' ) ? 'true' : 'false';
    $scale         = $scale();
    $trimZeros     = $trimsZeros() ? 'true' : 'false';
    $controlId     = $id ?? $model;
    $formatErrorId = $controlId . '-format-error';
    $hasServerError = isset( $errors ) && $errors instanceof \Illuminate\Support\ViewErrorBag && $errors->has( $model );
    $serverInvalid  = $hasServerError ? "'true'" : 'null';
    // While the format error shows, the hidden server error drops out of the description.
    $describedIds   = array_filter( [ null !== $hint && '' !== $hint ? $controlId . '-hint' : null, $hasServerError ? $controlId . '-error' : null ] );
    $describedBy    = \Illuminate\Support\Js::from( implode( ' ', [ ...$describedIds, $formatErrorId ] ) )->toHtml();
    $describedByFormat = \Illuminate\Support\Js::from( implode( ' ', [ ...array_filter( $describedIds, static fn ( string $id ): bool => ! str_ends_with( $id, '-error' ) ), $formatErrorId ] ) )->toHtml();
@endphp
<div
    x-data="{
        value: $wire.entangle( @js( $model ), {{ $live }} ),
        scale: {{ $scale }},
        trimZeros: {{ $trimZeros }},
        display: '',
        invalid: false,
        init() {
            this.display = this.format( this.value );
            this.$watch( 'value', ( value ) => {
                if ( this.invalid && null === this.normalize( value ) ) {
                    return;
                }
                if ( this.parse( this.display ) !== this.normalize( value ) ) {
                    this.display  = this.format( value );
                    this.invalid  = false;
                }
            } );
        },
        normalize( value ) {
            return null === value || undefined === value || '' === value ? null : String( value );
        },
        format( value ) {
            let digits = this.normalize( value );
            if ( null === digits ) {
                return '';
            }
            const negative = digits.startsWith( '-' );
            digits = digits.replace( '-', '' );
            if ( 0 === this.scale ) {
                return ( negative ? '-' : '' ) + digits;
            }
            digits = digits.padStart( this.scale + 1, '0' );
            let fraction = digits.slice( -this.scale );
            if ( this.trimZeros ) {
                fraction = fraction.replace( /0+$/, '' );
            }
            return ( negative ? '-' : '' ) + digits.slice( 0, -this.scale ) + ( '' === fraction ? '' : '.' + fraction );
        },
        parse( input ) {
            let text = String( input ?? '' ).replace( /[\s  ']/g, '' );
            if ( '' === text ) {
                return null;
            }
            const negative = text.startsWith( '-' );
            text = text.replace( /^-+/, '' );
            if ( ! /^[0-9.,]+$/.test( text ) || ! /[0-9]/.test( text ) ) {
                return false;
            }
            const lastDot   = text.lastIndexOf( '.' );
            const lastComma = text.lastIndexOf( ',' );
            let separator   = null;
            if ( -1 !== lastDot && -1 !== lastComma ) {
                separator = lastDot > lastComma ? '.' : ',';
            } else if ( -1 !== lastDot || -1 !== lastComma ) {
                const candidate   = -1 !== lastDot ? '.' : ',';
                const groups      = text.split( candidate );
                const occurrences = groups.length - 1;
                const isGrouping  = groups[0].length >= 1 && groups[0].length <= 3 && ! groups[0].startsWith( '0' )
                    && groups.slice( 1 ).every( ( group ) => 3 === group.length );
                if ( ! ( isGrouping && ( occurrences > 1 || this.scale < 3 ) ) ) {
                    if ( 1 !== occurrences ) {
                        return false;
                    }
                    separator = candidate;
                }
            }
            let whole    = text;
            let fraction = '';
            if ( null !== separator ) {
                whole    = text.slice( 0, text.lastIndexOf( separator ) );
                fraction = text.slice( text.lastIndexOf( separator ) + 1 );
            }
            whole = whole.replace( /[.,]/g, '' );
            if ( /[.,]/.test( fraction ) || fraction.length > this.scale ) {
                return false;
            }
            let digits = ( ( '' === whole ? '0' : whole ) + fraction.padEnd( this.scale, '0' ) ).replace( /^0+/, '' );
            digits = '' === digits ? '0' : digits;
            if ( digits.length > 15 ) {
                return false;
            }
            return ( negative && '0' !== digits ? '-' : '' ) + digits;
        },
        commit() {
            const parsed = this.parse( this.display );
            if ( false === parsed ) {
                // Clear the bound value so a submit fails server validation
                // instead of silently saving the previous amount.
                this.invalid = true;
                this.value   = null;
                return;
            }
            this.invalid = false;
            this.value   = null === parsed ? null : parseInt( parsed, 10 );
            this.display = this.format( this.value );
        },
    }"
    data-scale="{{ $scale }}"
    x-bind:data-format-invalid="invalid ? '' : null"
    {{ $attributes->only( [ 'class', 'wire:key' ] )->class( [ 'ec-scaled-input' ] ) }}
>
    <x-artisanpack-input
        :id="$controlId"
        :hint="$hint"
        :prefix="$prefix"
        :suffix="$suffix"
        :error-field="$model"
        x-model="display"
        x-on:blur="commit()"
        x-on:keydown.enter="commit()"
        x-bind:aria-invalid="invalid ? 'true' : {{ $serverInvalid }}"
        x-bind:aria-describedby="invalid ? {{ $describedByFormat }} : {{ $describedBy }}"
        :aria-describedby="$formatErrorId"
        inputmode="decimal"
        autocomplete="off"
        {{ $attributes->whereDoesntStartWith( 'wire:model' )->except( [ 'class', 'wire:key' ] ) }}
    >
        @if ( null !== $accessibleLabel() )
            <x-slot:label>{{ $accessibleLabel() }}</x-slot:label>
        @endif
    </x-artisanpack-input>

    {{-- The format error takes the server error's slot: the stylesheet hides the server one while this shows. --}}
    <p id="{{ $formatErrorId }}" data-scope="input" data-part="error" data-format-error role="alert" hidden x-bind:hidden="! invalid"><x-artisanpack-icon name="circle-alert" aria-hidden="true" /><span x-text="invalid ? @js( $invalidMessage() ) : ''"></span></p>
</div>
