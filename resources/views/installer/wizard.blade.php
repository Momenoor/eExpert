<div>
    <div class="steps">
        @foreach ([
            1 => __('Requirements'),
            2 => __('Database'),
            3 => __('Application'),
            4 => __('Install'),
            5 => __('Admin'),
            6 => __('Done'),
        ] as $number => $label)
            <div class="dot @if($step === $number) active @elseif($step > $number) done @endif">
                {{ $number }}. {{ $label }}
            </div>
        @endforeach
    </div>

    {{-- Step 1: Server requirements --}}
    @if ($step === 1)
        <div class="card">
            <h2>{{ __('Server Requirements') }}</h2>
            <p class="hint">{{ __('These must all pass before the installer can continue.') }}</p>

            <ul class="check-list">
                @foreach ($this->requirementChecks as $check)
                    <li>
                        <span>{{ $check['label'] }} — <span class="hint">{{ $check['detail'] }}</span></span>
                        <span class="badge {{ $check['ok'] ? 'ok' : ($check['critical'] ? 'fail' : 'warn') }}">
                            {{ $check['ok'] ? __('OK') : ($check['critical'] ? __('Failing') : __('Warning')) }}
                        </span>
                    </li>
                @endforeach
            </ul>

            @error('requirements')
                <div class="alert danger">{{ $message }}</div>
            @enderror

            <div class="actions">
                <button type="button" class="btn" wire:click="continueFromRequirements">
                    {{ __('Continue') }}
                </button>
            </div>
        </div>
    @endif

    {{-- Step 2: Database configuration --}}
    @if ($step === 2)
        <div class="card">
            <h2>{{ __('Database Configuration') }}</h2>

            <div class="field">
                <label>{{ __('Connection') }}</label>
                <select wire:model.live="db_connection">
                    <option value="mysql">MySQL</option>
                    <option value="sqlite">SQLite</option>
                </select>
            </div>

            @if ($db_connection === 'mysql')
                <div class="row">
                    <div class="field">
                        <label>{{ __('Host') }}</label>
                        <input type="text" wire:model="db_host">
                        @error('db_host') <div class="error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label>{{ __('Port') }}</label>
                        <input type="text" wire:model="db_port">
                        @error('db_port') <div class="error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="field">
                    <label>{{ __('Database Name') }}</label>
                    <input type="text" wire:model="db_database">
                    @error('db_database') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="row">
                    <div class="field">
                        <label>{{ __('Username') }}</label>
                        <input type="text" wire:model="db_username">
                    </div>
                    <div class="field">
                        <label>{{ __('Password') }}</label>
                        <input type="password" wire:model="db_password">
                    </div>
                </div>
            @else
                <div class="field">
                    <label>{{ __('Database File Path') }}</label>
                    <input type="text" wire:model="db_database" placeholder="{{ database_path('database.sqlite') }}">
                    @error('db_database') <div class="error">{{ $message }}</div> @enderror
                </div>
            @endif

            @if ($connectionTested === true)
                <div class="alert success">{{ $connectionMessage }}</div>
            @elseif ($connectionTested === false)
                <div class="alert danger">{{ $connectionMessage }}</div>
            @endif

            <div class="actions">
                <button type="button" class="btn secondary" wire:click="testConnection" wire:loading.attr="disabled">
                    {{ __('Test Connection') }}
                </button>
                <button type="button" class="btn" wire:click="saveDatabaseAndContinue">
                    {{ __('Save & Continue') }}
                </button>
            </div>
        </div>
    @endif

    {{-- Step 3: Application details --}}
    @if ($step === 3)
        <div class="card">
            <h2>{{ __('Application Details') }}</h2>

            <div class="field">
                <label>{{ __('Application Name') }}</label>
                <input type="text" wire:model="app_name">
                @error('app_name') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label>{{ __('Application URL') }}</label>
                <input type="text" wire:model="app_url" placeholder="https://example.com">
                <p class="hint">{{ __('Mail and other settings can be configured after installation, from Settings in the admin panel.') }}</p>
                @error('app_url') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="actions">
                <button type="button" class="btn" wire:click="saveAppSettingsAndContinue">
                    {{ __('Continue') }}
                </button>
            </div>
        </div>
    @endif

    {{-- Step 4: Migrate & seed --}}
    @if ($step === 4)
        <div class="card">
            <h2>{{ __('Install the Database') }}</h2>
            <p class="hint">{{ __('This creates every table the application needs and seeds permissions and default roles. It can take a moment.') }}</p>

            @if ($migrationOutput !== '')
                <pre class="output">{{ $migrationOutput }}</pre>
            @endif

            @if ($migrationFailed)
                <div class="alert danger">{{ __('Installation failed. Fix the issue above and try again.') }}</div>
            @endif

            <div class="actions">
                <button type="button" class="btn secondary" wire:click="runMigrations" wire:loading.attr="disabled" wire:target="runMigrations">
                    <span wire:loading.remove wire:target="runMigrations">{{ __('Run Installation') }}</span>
                    <span wire:loading wire:target="runMigrations">{{ __('Running…') }}</span>
                </button>
                <button type="button" class="btn" wire:click="continueFromMigration" @disabled(! $migrated)>
                    {{ __('Continue') }}
                </button>
            </div>
        </div>
    @endif

    {{-- Step 5: Admin account --}}
    @if ($step === 5)
        <div class="card">
            <h2>{{ __('Create the Administrator Account') }}</h2>

            <div class="field">
                <label>{{ __('Name') }}</label>
                <input type="text" wire:model="admin_name">
                @error('admin_name') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label>{{ __('Email') }}</label>
                <input type="email" wire:model="admin_email">
                @error('admin_email') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="row">
                <div class="field">
                    <label>{{ __('Password') }}</label>
                    <input type="password" wire:model="admin_password">
                    @error('admin_password') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label>{{ __('Confirm Password') }}</label>
                    <input type="password" wire:model="admin_password_confirmation">
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn" wire:click="createAdmin">
                    {{ __('Create Account & Continue') }}
                </button>
            </div>
        </div>
    @endif

    {{-- Step 6: Complete --}}
    @if ($step === 6)
        <div class="card">
            <h2>{{ __('Installation Complete') }}</h2>
            <p>{{ __('The application is ready. This setup wizard will no longer be reachable once you continue.') }}</p>

            <div class="actions">
                <button type="button" class="btn" wire:click="finish">
                    {{ __('Go to Login') }}
                </button>
            </div>
        </div>
    @endif
</div>
