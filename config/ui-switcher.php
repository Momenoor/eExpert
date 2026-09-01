<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Driver
    |--------------------------------------------------------------------------
    | Where to store UI preferences.
    |
    | Options:
    | - 'session' (default): Preferences stored in user session (lost on logout)
    | - 'database': Preferences stored in users table (persists across sessions)
    |
    | For database storage, you must:
    | 1. Publish and run the migration: php artisan vendor:publish --tag=filament-ui-switcher-migrations
    | 2. Add HasUiPreferences trait to your User model
    | 3. Add 'ui_preferences' => 'array' to your User model's $casts
    */
    'driver' => env('UI_SWITCHER_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Database Column
    |--------------------------------------------------------------------------
    | Only used if driver = 'database'.
    | The column on the users table where preferences are stored as JSON.
    |
    | Default: 'ui_preferences'
    */
    'database_column' => 'ui_preferences',

    /*
    |--------------------------------------------------------------------------
    | Default Preferences
    |--------------------------------------------------------------------------
    | Default values for UI preferences.
    | Can be overridden through the settings panel.
    */
    'defaults' => [
        'font' => 'Inter',
        'color' => '#6366f1',
        'layout' => 'sidebar',
        'font_size' => 16,
        'density' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Icon for Settings Panel
    |--------------------------------------------------------------------------
    | Default Icon.
    */
    'icon' => 'heroicon-o-cog-6-tooth',

    /*
    |--------------------------------------------------------------------------
    | Available Fonts
    |--------------------------------------------------------------------------
    | List of Google Fonts available in the font selector.
    | All fonts must be available on Google Fonts.
    */
    'fonts' => [
        'Inter',
        'Poppins',
        'Public Sans',
        'DM Sans',
        'Nunito Sans',
        'Roboto',
        'Boutros MBC Dinkum',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Colors
    |--------------------------------------------------------------------------
    | Colors shown in the color picker.
    */
    'custom_colors' => [
        '#6366f1', '#3b82f6', '#0ea5e9', '#10b981',
        '#22c55e', '#84cc16', '#eab308', '#f59e0b',
        '#f97316', '#ef4444', '#ec4899', '#8b5cf6',
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Layouts
    |--------------------------------------------------------------------------
    | Layout options available to users.
    |
    | Options:
    | - 'sidebar': Full sidebar with icons and labels
    | - 'sidebar-collapsed': Collapsed sidebar (icons only)
    | - 'sidebar-no-topbar': Sidebar without topbar (Filament v4.1+)
    | - 'topbar': Top navigation bar
    */
    'layouts' => [
        'sidebar',
        'sidebar-collapsed',
        'sidebar-no-topbar',
        'topbar',
    ],

    /*
    |--------------------------------------------------------------------------
    | Font Size Range
    |--------------------------------------------------------------------------
    | Min and max values for the font size slider (in pixels).
    */
    'font_size_range' => [
        'min' => 12,
        'max' => 24,
    ],
];
