<?php

/**
 * Notification template editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceAdminLivewire\Livewire\Notifications;

use ArtisanPackUI\Ecommerce\Exceptions\NotificationTemplateException;
use ArtisanPackUI\Ecommerce\Mail\NotificationTemplateMail;
use ArtisanPackUI\Ecommerce\Models\NotificationTemplate;
use ArtisanPackUI\Ecommerce\Services\NotificationTemplateService;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\AuthorizesEcommerce;
use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Concerns\SendsToasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use JsonException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The notification template editor (spec §7.8).
 *
 * - Subject and body are Twig source. Every edit re-renders the preview
 *   through `NotificationTemplateService::preview()` against the editable
 *   preview data, so sandbox and undeclared-variable errors show under the
 *   field as they are typed. A save runs the same validation through
 *   `NotificationTemplateService::update()` and is refused while any remain.
 * - The variables panel lists the template's declared variables; clicking
 *   one inserts it where the cursor was last (subject or body).
 * - The locale switcher moves between the template's translations, and a
 *   new translation starts as a copy of the current one.
 * - "Reset to default" restores the catalog's subject and body.
 * - "Send test" mails the current, unsaved preview to the signed-in admin.
 * - Sources and preview data are size-capped before every render, and only
 *   users who may update the template re-render it.
 *
 * The HTML preview renders in an `<iframe sandbox="">`, so template markup
 * can never run script in the admin.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceAdminLivewire
 *
 * @since      1.0.0
 */
class Edit extends Component
{
    use AuthorizesEcommerce;
    use SendsToasts;

    /**
     * Test sends allowed per user per minute.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const TEST_SENDS_PER_MINUTE = 5;

    /**
     * Locales a translation can always be added in, besides those in use.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const DEFAULT_LOCALES = [ 'en', 'es', 'fr', 'de' ];

    /**
     * Longest subject source, in characters.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_SUBJECT = 2000;

    /**
     * Longest body source, in characters.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_BODY = 100000;

    /**
     * Largest preview data, in bytes of JSON.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_PREVIEW_BYTES = 65536;

    /**
     * Most entries any one list or object in the preview data may have, so
     * nested loops in a template can't be made to run for minutes.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_PREVIEW_ITEMS = 100;

    /**
     * The template row being edited.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $templateId;

    /**
     * Subject source.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $subject = '';

    /**
     * Body source.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $body = '';

    /**
     * Preview data as JSON.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $previewJson = '{}';

    /**
     * Whether the template is sent.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $isActive = true;

    /**
     * The locale switcher's selection (a sibling row id).
     *
     * @since 1.0.0
     *
     * @var int|string|null
     */
    public int|string|null $localeId = null;

    /**
     * The locale a new translation is added in.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public string $newLocale = '';

    /**
     * Whether the reset-to-default confirmation is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $confirmingReset = false;

    /**
     * Rendered preview subject.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    #[Locked]
    public ?string $previewSubject = null;

    /**
     * Bumped on every preview render, so the preview's live region is read
     * again even when its message is unchanged.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $previewRevision = 0;

    /**
     * Rendered preview body.
     *
     * @since 1.0.0
     *
     * @var string
     */
    #[Locked]
    public string $previewBody = '';

    /**
     * Problems found while rendering the preview.
     *
     * @since 1.0.0
     *
     * @var array<int, array{field: string, message: string}>
     */
    #[Locked]
    public array $previewErrors = [];

    /**
     * The loaded row, per request.
     *
     * @since 1.0.0
     *
     * @var NotificationTemplate|null
     */
    private ?NotificationTemplate $loadedTemplate = null;

    /**
     * Loads and authorizes the template, then renders its preview.
     *
     * @since 1.0.0
     *
     * @param  int|string  $template  Template row id.
     *
     * @return void
     */
    public function mount( int|string $template ): void
    {
        $this->templateId = (int) $template;

        $row = $this->template();
        $this->authorizeEcommerce( 'view', $row );

        $this->fillFrom( $row );
        $this->renderPreview();
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
        $this->authorizeEcommerce( 'view', $this->template() );
    }

    /**
     * Re-renders the preview when a source or the preview data changes, and
     * saves the active toggle or switches locale when those change.
     *
     * @since 1.0.0
     *
     * @param  string  $property  The updated property.
     *
     * @return void
     */
    public function updated( string $property ): void
    {
        if ( in_array( $property, [ 'subject', 'body', 'previewJson' ], true ) ) {
            // Only editors re-render: a view-only user sees the stored copy,
            // so their edits are dropped instead of being rendered.
            if ( ! $this->canEcommerce( 'update', $this->template() ) ) {
                $this->fillFrom( $this->template() );

                return;
            }

            $this->renderPreview();

            return;
        }

        if ( 'isActive' === $property ) {
            $this->saveActive();

            return;
        }

        if ( 'localeId' === $property ) {
            $this->switchLocale();
        }
    }

    /**
     * Saves the subject, body, and preview data.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function save(): void
    {
        $template = $this->template();
        $this->authorizeEcommerce( 'update', $template );

        $this->validate(
            [
                'subject'     => [ 'nullable', 'string', 'max:' . self::MAX_SUBJECT ],
                'body'        => [ 'required', 'string', 'max:' . self::MAX_BODY ],
                'previewJson' => [ 'required', 'string', 'max:' . self::MAX_PREVIEW_BYTES ],
            ],
            [],
            [ 'subject' => __( 'subject' ), 'body' => __( 'body' ), 'previewJson' => __( 'preview data' ) ],
        );

        $limit = $this->limitError();

        if ( null !== $limit ) {
            $this->addError( $limit['field'], $limit['message'] );

            return;
        }

        $data = $this->previewData();

        if ( null === $data ) {
            $this->addError( 'previewJson', __( 'The preview data must be a JSON object.' ) );

            return;
        }

        $attributes = [ 'body' => $this->body, 'preview_data' => $data ];

        if ( $this->hasSubject() ) {
            $attributes['subject'] = $this->subject;
        }

        try {
            app( NotificationTemplateService::class )->update( $template, $attributes );
        } catch ( NotificationTemplateException $exception ) {
            $this->reportErrors( $exception );
            $this->toastError( __( 'The template was not saved.' ), __( 'Fix the errors shown under the fields.' ) );

            return;
        }

        $this->renderPreview();
        $this->toastSuccess( __( 'Template saved.' ) );
    }

    /**
     * Asks to confirm resetting to the default copy.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function confirmReset(): void
    {
        $this->authorizeEcommerce( 'update', $this->template() );

        $this->confirmingReset = null !== $this->template()->definition();
    }

    /**
     * Dismisses the reset confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelReset(): void
    {
        $this->confirmingReset = false;
    }

    /**
     * Restores the catalog's default subject and body.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function resetToDefault(): void
    {
        $template = $this->template();
        $this->authorizeEcommerce( 'update', $template );

        $this->confirmingReset = false;
        $definition            = $template->definition();

        if ( null === $definition ) {
            $this->toastError( __( 'This template has no default copy.' ) );

            return;
        }

        $attributes = [ 'body' => $definition->defaultBody() ];

        if ( $this->hasSubject() ) {
            $attributes['subject'] = $definition->defaultSubject();
        }

        try {
            app( NotificationTemplateService::class )->update( $template, $attributes );
        } catch ( NotificationTemplateException $exception ) {
            $this->reportErrors( $exception );

            return;
        }

        $this->subject = (string) ( $template->subject ?? '' );
        $this->body    = (string) $template->body;
        $this->resetErrorBag();
        $this->renderPreview();
        $this->toastSuccess( __( 'Template reset to the default copy.' ) );
    }

    /**
     * Adds a translation in `newLocale`, copied from this one, and opens it.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function addLocale(): void
    {
        $template = $this->template();
        $this->authorizeEcommerce( 'update', $template );

        $this->validate(
            [ 'newLocale' => [ 'required', 'string', Rule::in( $this->availableLocales() ) ] ],
            [ 'newLocale.in' => __( 'Choose a language this template is not translated into yet.' ) ],
            [ 'newLocale' => __( 'language' ) ],
        );

        $copy = NotificationTemplate::query()->createOrFirst(
            [ 'key' => $template->key, 'channel' => $template->channel, 'locale' => $this->newLocale ],
            [
                'subject'      => $template->subject,
                'body'         => $template->body,
                'variables'    => $template->variables,
                'preview_data' => $template->preview_data,
                'is_active'    => $template->is_active,
            ],
        );

        $this->newLocale = '';
        $this->toastSuccess( __( 'Translation added. Edit it below.' ) );
        $this->redirectRoute( 'artisanpack.ecommerce.admin.notifications.edit', [ 'template' => $copy->id ] );
    }

    /**
     * Mails the current preview to the signed-in admin.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function sendTest(): void
    {
        $template = $this->template();
        $this->authorizeEcommerce( 'update', $template );

        $user  = auth()->user();
        $email = is_object( $user ) ? (string) ( $user->email ?? '' ) : '';

        if ( 'mail' !== $template->channel ) {
            $this->toastError( __( 'Only email templates can be sent as a test.' ) );

            return;
        }

        if ( '' === $email || false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            $this->toastError( __( 'Your account has no email address to send the test to.' ) );

            return;
        }

        $this->renderPreview();

        if ( [] !== $this->previewErrors ) {
            $this->toastError( __( 'The test was not sent.' ), __( 'Fix the errors shown under the fields first.' ) );

            return;
        }

        $key = 'ecommerce-admin:notification-test:' . auth()->id();

        if ( RateLimiter::tooManyAttempts( $key, self::TEST_SENDS_PER_MINUTE ) ) {
            $this->toastWarning(
                __( 'Too many test emails.' ),
                __( 'Try again in :seconds seconds.', [ 'seconds' => RateLimiter::availableIn( $key ) ] ),
            );

            return;
        }

        RateLimiter::hit( $key, 60 );

        try {
            Mail::to( $email )->send( new NotificationTemplateMail(
                (string) $template->key,
                __( '[Test] :subject', [ 'subject' => (string) $this->previewSubject ] ),
                $this->previewBody,
            ) );
        } catch ( Throwable $exception ) {
            report( $exception );
            $this->toastError( __( 'The test email could not be sent.' ), __( 'Check the mail settings and try again.' ) );

            return;
        }

        $this->toastSuccess( __( 'Test sent to :email.', [ 'email' => $email ] ) );
    }

    /**
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $template   = $this->template();
        $definition = $template->definition();
        $service    = app( NotificationTemplateService::class );

        $siblings = NotificationTemplate::query()
            ->where( 'key', $template->key )
            ->where( 'channel', $template->channel )
            ->orderBy( 'locale' )
            ->get( [ 'id', 'locale' ] );

        return view( 'ecommerce-admin::livewire.notifications.edit', [
            'template'      => $template,
            'label'         => null === $definition ? Str::headline( (string) $template->key ) : (string) $definition->label(),
            'hasSubject'    => $this->hasSubject(),
            'isHtml'        => 'mail' === $template->channel,
            'hasDefault'    => null !== $definition,
            'canUpdate'     => $this->canEcommerce( 'update', $template ),
            'variables'     => self::variableSnippets( $service->declaredVariables( $template ) ),
            'localeOptions' => $siblings->map( static fn ( NotificationTemplate $row ): array => [ 'id' => (int) $row->id, 'name' => Index::localeLabel( (string) $row->locale ) ] )->all(),
            'newLocales'    => array_map( static fn ( string $locale ): array => [ 'id' => $locale, 'name' => Index::localeLabel( $locale ) ], $this->availableLocales() ),
            'channelLabel'  => Index::channelLabel( (string) $template->channel ),
            'indexUrl'      => route( 'artisanpack.ecommerce.admin.notifications.index' ),
        ] );
    }

    /**
     * Each declared variable with the Twig to insert for it. Paths through a
     * list (`Order.items.*.name`) insert a loop over the list.
     *
     * @since 1.0.0
     *
     * @param  array<int, string>  $paths  Declared variable paths.
     *
     * @return array<int, array{path: string, snippet: string}>
     */
    public static function variableSnippets( array $paths ): array
    {
        $snippets = [];

        foreach ( $paths as $path ) {
            $path = (string) $path;

            if ( str_contains( $path, '.*.' ) ) {
                [ $list, $field ] = explode( '.*.', $path, 2 );
                $snippet          = '{% for item in ' . $list . ' %}{{ item.' . $field . ' }}{% endfor %}';
            } elseif ( str_ends_with( $path, '.*' ) ) {
                $snippet = '{% for item in ' . substr( $path, 0, -2 ) . ' %}{{ item }}{% endfor %}';
            } else {
                $snippet = '{{ ' . $path . ' }}';
            }

            $snippets[] = [ 'path' => $path, 'snippet' => $snippet ];
        }

        return $snippets;
    }

    /**
     * Renders the preview from the current sources and preview data, and
     * puts any problems under their fields.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function renderPreview(): void
    {
        $this->previewRevision++;
        $this->previewErrors = [];
        $this->resetErrorBag( [ 'subject', 'body', 'previewJson' ] );

        $limit = $this->limitError();

        if ( null !== $limit ) {
            $this->previewErrors[] = $limit;
            $this->addError( $limit['field'], $limit['message'] );

            return;
        }

        $data = $this->previewData();

        if ( null === $data ) {
            $this->previewErrors[] = [ 'field' => 'previewJson', 'message' => __( 'The preview data must be a JSON object.' ) ];
            $this->addError( 'previewJson', __( 'The preview data must be a JSON object.' ) );

            return;
        }

        try {
            $rendered = app( NotificationTemplateService::class )->preview(
                $this->template(),
                $this->hasSubject() ? $this->subject : null,
                $this->body,
                $data,
            );
        } catch ( NotificationTemplateException $exception ) {
            $this->reportErrors( $exception );

            return;
        }

        $this->previewSubject = $rendered['subject'];
        $this->previewBody    = $rendered['body'];
    }

    /**
     * Puts an engine template error list under the matching fields.
     *
     * @since 1.0.0
     *
     * @param  NotificationTemplateException  $exception  The error.
     *
     * @return void
     */
    protected function reportErrors( NotificationTemplateException $exception ): void
    {
        $this->previewErrors = [];

        foreach ( $exception->errors as $error ) {
            $field = match ( (string) ( $error['field'] ?? '' ) ) {
                'subject'      => 'subject',
                'preview_data' => 'previewJson',
                default        => 'body',
            };
            $message = (string) ( $error['message'] ?? $exception->getMessage() );

            $this->previewErrors[] = [ 'field' => $field, 'message' => $message ];
            $this->addError( $field, $message );
        }
    }

    /**
     * The first size limit the sources or preview data break, checked before
     * anything is rendered.
     *
     * @since 1.0.0
     *
     * @return array{field: string, message: string}|null
     */
    protected function limitError(): ?array
    {
        if ( mb_strlen( $this->subject ) > self::MAX_SUBJECT ) {
            return [ 'field' => 'subject', 'message' => __( 'Keep the subject to :max characters or fewer.', [ 'max' => self::MAX_SUBJECT ] ) ];
        }

        if ( mb_strlen( $this->body ) > self::MAX_BODY ) {
            return [ 'field' => 'body', 'message' => __( 'Keep the body to :max characters or fewer.', [ 'max' => self::MAX_BODY ] ) ];
        }

        if ( strlen( $this->previewJson ) > self::MAX_PREVIEW_BYTES ) {
            return [ 'field' => 'previewJson', 'message' => __( 'Keep the preview data to :max KB or less.', [ 'max' => intdiv( self::MAX_PREVIEW_BYTES, 1024 ) ] ) ];
        }

        $data = $this->previewData();

        if ( null !== $data && self::exceedsItems( $data ) ) {
            return [ 'field' => 'previewJson', 'message' => __( 'Lists in the preview data can have at most :max items.', [ 'max' => self::MAX_PREVIEW_ITEMS ] ) ];
        }

        return null;
    }

    /**
     * Whether any array in `$data`, at any depth, has more than
     * {@see self::MAX_PREVIEW_ITEMS} entries.
     *
     * @since 1.0.0
     *
     * @param  array<mixed>  $data  Decoded preview data.
     *
     * @return bool
     */
    protected static function exceedsItems( array $data ): bool
    {
        if ( count( $data ) > self::MAX_PREVIEW_ITEMS ) {
            return true;
        }

        foreach ( $data as $value ) {
            if ( is_array( $value ) && self::exceedsItems( $value ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The preview data, or null when it is not a JSON object.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>|null
     */
    protected function previewData(): ?array
    {
        $json = trim( $this->previewJson );

        if ( '' === $json ) {
            return [];
        }

        try {
            $data = json_decode( $json, true, 64, JSON_THROW_ON_ERROR );
        } catch ( JsonException ) {
            return null;
        }

        if ( ! is_array( $data ) || ( [] !== $data && array_is_list( $data ) ) ) {
            return null;
        }

        return $data;
    }

    /**
     * Saves the active toggle.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function saveActive(): void
    {
        $template = $this->template();
        $this->authorizeEcommerce( 'update', $template );

        app( NotificationTemplateService::class )->update( $template, [ 'is_active' => $this->isActive ] );

        $this->toastSuccess( $this->isActive ? __( 'Template turned on.' ) : __( 'Template turned off. It will not be sent.' ) );
    }

    /**
     * Opens the translation picked in the locale switcher.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function switchLocale(): void
    {
        $template = $this->template();
        $target   = is_numeric( $this->localeId )
            ? NotificationTemplate::query()
                ->whereKey( (int) $this->localeId )
                ->where( 'key', $template->key )
                ->where( 'channel', $template->channel )
                ->first()
            : null;

        $this->localeId = $this->templateId;

        if ( null === $target || (int) $target->id === $this->templateId ) {
            return;
        }

        $this->authorizeEcommerce( 'view', $target );
        $this->redirectRoute( 'artisanpack.ecommerce.admin.notifications.edit', [ 'template' => $target->id ] );
    }

    /**
     * Locales the template is not translated into yet.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    protected function availableLocales(): array
    {
        $template = $this->template();
        $used     = NotificationTemplate::query()
            ->where( 'key', $template->key )
            ->where( 'channel', $template->channel )
            ->pluck( 'locale' )
            ->map( static fn ( mixed $locale ): string => (string) $locale )
            ->all();

        $locales = (array) applyFilters(
            'ap.ecommerceAdminLivewire.notifications.locales',
            array_values( array_unique( [ ...self::DEFAULT_LOCALES, app( NotificationTemplateService::class )->defaultLocale() ] ) ),
        );

        $locales = array_filter(
            array_map( static fn ( mixed $locale ): string => (string) $locale, $locales ),
            static fn ( string $locale ): bool => 1 === preg_match( '/^[A-Za-z]{2,3}([_-][A-Za-z0-9]{2,8})*$/', $locale ),
        );

        return array_values( array_diff( array_unique( $locales ), $used ) );
    }

    /**
     * Whether the channel carries a subject line.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function hasSubject(): bool
    {
        $template = $this->template();

        return 'mail' === $template->channel || null !== $template->subject;
    }

    /**
     * Copies a row into the form.
     *
     * @since 1.0.0
     *
     * @param  NotificationTemplate  $template  Row.
     *
     * @return void
     */
    protected function fillFrom( NotificationTemplate $template ): void
    {
        $this->subject     = (string) ( $template->subject ?? '' );
        $this->body        = (string) $template->body;
        $this->isActive    = (bool) $template->is_active;
        $this->localeId    = (int) $template->id;
        $this->previewJson = (string) json_encode(
            (object) ( (array) $template->preview_data ),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * The row being edited.
     *
     * @since 1.0.0
     *
     * @return NotificationTemplate
     */
    protected function template(): NotificationTemplate
    {
        if ( null === $this->loadedTemplate ) {
            $this->loadedTemplate = NotificationTemplate::query()->find( $this->templateId ) ?? abort( 404 );
        }

        return $this->loadedTemplate;
    }
}
