<?php

namespace App\Filament\Resources\Parties\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class PartyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema

            ->components([
                // ── Outer 3-column grid ────────────────────────────────────
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([

                        // ── Left 2 columns: Identity + Roles + Contact ─────
                        Grid::make(2)
                            ->schema([

                                // Row 1 left: Identity
                                Section::make(__('Identity'))
                                    ->icon('heroicon-o-user')
                                    ->schema([
                                        TextEntry::make('name')
                                            ->size(TextSize::Large)
                                            ->weight(FontWeight::Bold)
                                            ->columnSpanFull(),

                                        IconEntry::make('black_list')
                                            ->label(__('Is Blacklisted'))
                                            ->boolean()
                                            ->trueColor('danger')
                                            ->falseColor('success')
                                            ->trueIcon('heroicon-o-check-circle')
                                            ->falseIcon('heroicon-o-x-circle'),

                                    ])
                                    ->columns(2)
                                    ->columnSpan(1),

                                // Row 1 right: Roles & Expertise
                                Section::make(__('Roles & Expertise'))
                                    ->icon('heroicon-o-briefcase')
                                    ->schema([
                                        TextEntry::make('role')
                                            ->label(__('Roles'))
                                            ->badge()
                                            ->color('info')
                                            ->getStateUsing(function ($record) {
                                                $role = $record->role;
                                                if (empty($role)) {
                                                    return [];
                                                }
                                                if (isset($role['role'])) {
                                                    $roles = (array) ($role['role'] ?? []);
                                                } else {
                                                    $roles = collect($role)
                                                        ->pluck('role')
                                                        ->unique()
                                                        ->values()
                                                        ->toArray();
                                                }

                                                return array_map(fn ($r) => __($r ? ucfirst($r) : ''), $roles);
                                            })
                                            ->columnSpanFull(),

                                        TextEntry::make('expert_types')
                                            ->label(__('Expert Types'))
                                            ->badge()
                                            ->color('warning')
                                            ->getStateUsing(function ($record) {
                                                $role = $record->role;
                                                if (empty($role)) {
                                                    return [];
                                                }
                                                if (isset($role['type'])) {
                                                    $types = (array) ($role['type'] ?? []);
                                                } else {
                                                    $types = collect($role)
                                                        ->where('role', 'expert')
                                                        ->pluck('type')
                                                        ->filter()
                                                        ->unique()
                                                        ->values()
                                                        ->toArray();
                                                }

                                                return array_map(fn ($t) => __($t ? ucfirst($t) : ''), $types);
                                            })
                                            ->visible(fn ($record) => $record->role && $record->isExpert())
                                            ->placeholder('—')
                                            ->columnSpanFull(),

                                        TextEntry::make('expertise_field')
                                            ->label(__('Expertise Field'))
                                            ->icon('heroicon-o-academic-cap')
                                            ->getStateUsing(function ($record) {
                                                $role = $record->role;
                                                if (empty($role)) {
                                                    return null;
                                                }
                                                if (isset($role['field'])) {
                                                    $field = $role['field'];
                                                } else {
                                                    $field = collect($role)
                                                        ->where('role', 'expert')
                                                        ->pluck('field')
                                                        ->filter()
                                                        ->first();
                                                }

                                                return $field ? __($field ? ucfirst($field) : '') : null;
                                            })
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpan(1),

                                // Row 2: Contact — spans full 2 columns
                                Section::make(__('Contact'))
                                    ->icon('heroicon-o-phone')
                                    ->schema([
                                        TextEntry::make('phone')
                                            ->label(__('Phone'))
                                            ->icon('heroicon-o-phone')
                                            ->placeholder('—')
                                            ->badge()
                                            ->listWithLineBreaks()
                                            ->copyable(),

                                        TextEntry::make('fax')
                                            ->label(__('Fax'))
                                            ->icon('heroicon-o-printer')
                                            ->placeholder('—'),

                                        TextEntry::make('email')
                                            ->label(__('Email'))
                                            ->icon('heroicon-o-envelope')
                                            ->placeholder('—')
                                            ->badge()
                                            ->listWithLineBreaks()
                                            ->copyable(),

                                        TextEntry::make('address')
                                            ->label(__('Address'))
                                            ->icon('heroicon-o-map-pin')
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull(), // spans both inner columns

                            ])
                            ->columnSpan(2), // takes 2 of the outer 3 columns

                        // ── Right column: Additional Info ──────────────────
                        Section::make(__('Additional Info'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                TextEntry::make('old_id')
                                    ->label(__('Legacy ID'))
                                    ->numeric()
                                    ->placeholder('—'),

                                TextEntry::make('created_at')
                                    ->label(__('Created'))
                                    ->dateTime()
                                    ->icon('heroicon-o-calendar'),

                                TextEntry::make('updated_at')
                                    ->label(__('Last Updated'))
                                    ->dateTime()
                                    ->since()
                                    ->icon('heroicon-o-clock'),

                                TextEntry::make('extra')
                                    ->label(__('Extra'))
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ])
                            ->columns(1)
                            ->columnSpan(1), // takes 1 of the outer 3 columns
                    ]),
            ]);
    }
}
