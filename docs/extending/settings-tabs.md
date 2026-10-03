---
title: Settings Tabs
---

# Settings Tabs

The Settings screen has one tab per group. There are two ways to add one.

## Fields only: use the engine's settings registry

Every group in the engine's `SettingsRegistry` gets a tab with a form built from its definitions: the definition's type picks the input, its rules validate it, and the engine's `SettingsRepository` stores it. If your satellite only needs fields, add a group and definitions to the engine registry and you are done. No admin code is needed.

## Your own screen: SettingsTabRegistry

When a group needs its own component (a connect button, a test call, a custom layout), register it with `ArtisanPackUI\EcommerceAdminLivewire\Registries\SettingsTabRegistry`.

### Signature

```php
public function register( string $key, string|Closure $label, string $component, int $position = 100 ): void
public function unregister( string $key ): void
public function has( string $key ): bool
public function get( string $key ): ?array
public function all(): array
```

| Argument | Meaning |
| --- | --- |
| `$key` | Lower-case key (letters, digits, `-` or `_` between words). Also the URL segment: the tab is at `/settings/{key}`. |
| `$label` | The label, or a closure returning it so it is translated per request |
| `$component` | Your Livewire component name or class |
| `$position` | Lower positions come first. The engine's groups sit at 10–100. |

A key that matches an engine group replaces that group's generated form.

### Writing the component

The component is mounted with no parameters. The screen has already checked `settings.view`. Check `settings.update` (or your own abilities) before saving:

```php
use ArtisanPackUI\EcommerceAdminLivewire\Support\Authorization;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PayPalSettingsTab extends Component
{
    public bool $sandbox = true;

    public function mount(): void
    {
        abort_unless( Authorization::allows( auth()->user(), 'settings.view' ), 403 );

        $this->sandbox = (bool) app( PayPalSettings::class )->sandbox();
    }

    public function save(): void
    {
        abort_unless( Authorization::allows( auth()->user(), 'settings.update' ), 403 );

        app( PayPalSettings::class )->setSandbox( $this->sandbox );
    }

    public function render(): View
    {
        return view( 'paypal::admin.settings-tab' );
    }
}
```

```php
use ArtisanPackUI\EcommerceAdminLivewire\Registries\SettingsTabRegistry;
use Livewire\Livewire;

Livewire::component( 'paypal-settings-tab', PayPalSettingsTab::class );

app( SettingsTabRegistry::class )->register(
    'paypal',
    fn (): string => __( 'PayPal' ),
    'paypal-settings-tab',
    55,
);
```

Never show or store gateway credentials in a settings tab. Read them from the environment, as the core Payments tab does.
