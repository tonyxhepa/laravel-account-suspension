<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
        ->schema([
            Forms\Components\TextInput::make('name')
            ->required()
            ->maxLength(255),
        Forms\Components\TextInput::make('email')
            ->email()
            ->required()
            ->maxLength(255)
            // Ensure email is unique except for the current user being edited
            ->unique(ignoreRecord: true),
        Forms\Components\DateTimePicker::make('email_verified_at')
            ->label('Email Verified At')
            ->readOnly(), // Usually not manually set
        Forms\Components\TextInput::make('password')
            ->password()
            // Only require on create, not on edit unless changing
            ->required(fn (string $context): bool => $context === 'create')
            // Hash password before saving
            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
            // Don't load existing password hash into the form
            ->dehydrated(fn ($state) => filled($state))
            ->maxLength(255)
            ->helperText('Leave blank to keep current password.'),

        // --- Add the Suspension Field ---
        DateTimePicker::make('suspended_at')
            ->label('Suspended At (leave blank if active)')
            ->nullable() // Allows clearing the date to unsuspend
            ->helperText('Setting a date/time here will suspend the user.')
            // Optional: Disable for the currently logged-in admin to prevent self-suspension via form
            ->disabled(fn ($record) => $record && $record->id === auth()->id())
            // Optional: Hide completely if user cannot manage suspension (using Policies)
            // ->hidden(fn ($record) => auth()->user()->cannot('suspend', $record ?? User::class)),
    ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                ->searchable(),
            Tables\Columns\TextColumn::make('email')
                ->searchable(),
            Tables\Columns\TextColumn::make('email_verified_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true), // Hide by default
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true), // Hide by default

            // --- Add Suspension Status Column ---
            IconColumn::make('suspended_at')
                ->label('Status')
                ->boolean() // Treats non-null as true (suspended), null as false (active)
                ->trueIcon('heroicon-o-no-symbol')
                ->falseIcon('heroicon-o-check-circle')
                ->trueColor('danger')
                ->falseColor('success')
                ->tooltip(fn (User $record): ?string => $record->suspended_at ? 'Suspended on: ' . $record->suspended_at->format('M d, Y H:i') : 'Active')
                ->sortable(),
            // -----------------------------------

            // Optional: Show suspension date directly (can be toggled)
             Tables\Columns\TextColumn::make('suspended_at')
                 ->label('Suspended On')
                 ->dateTime()
                 ->sortable()
                 ->toggleable(isToggledHiddenByDefault: true), // Hide by default
            ])
            ->filters([
                TernaryFilter::make('suspended_at')
                ->label('Suspension Status')
                ->placeholder('All Users')
                ->trueLabel('Suspended Users')
                ->falseLabel('Active Users')
                ->queries(
                    true: fn (Builder $query) => $query->whereNotNull('suspended_at'),
                    false: fn (Builder $query) => $query->whereNull('suspended_at'),
                    blank: fn (Builder $query) => $query, // Show all when blank
                ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->visible(fn(User $record): bool => $record->id !== auth()->id()),
                // --- Add Suspend/Unsuspend Actions ---
                Action::make('suspend')
                ->label('Suspend')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Suspend User?')
                ->modalDescription('Are you sure? The user will be logged out and unable to log in.')
                // Use the helper method from the User model if available
                ->action(function (User $record) {
                    if ($record->id === auth()->id()) {
                        Notification::make()->warning()->title('Action Denied')->body('You cannot suspend yourself.')->send();
                        return;
                    }
                    $record->suspend(); // Assumes suspend() method exists
                    // Or manually: $record->update(['suspended_at' => Carbon::now()]);
                    Notification::make()->success()->title('User Suspended')->send();
                })
                // Only show this action if the user is NOT currently suspended AND not the current admin
                ->visible(fn (User $record): bool => !$record->isSuspended() && $record->id !== auth()->id()),
                // Optional: Add Policy check ->visible(fn (User $record): bool => !$record->isSuspended() && auth()->user()->can('suspend', $record)),


            Action::make('unsuspend')
                ->label('Unsuspend')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Unsuspend User?')
                ->modalDescription('Are you sure you want to lift the suspension?')
                // Use the helper method from the User model if available
                ->action(function (User $record) {
                    $record->unsuspend(); // Assumes unsuspend() method exists
                    // Or manually: $record->update(['suspended_at' => null]);
                    Notification::make()->success()->title('User Unsuspended')->send();
                })
                // Only show this action if the user IS currently suspended
                ->visible(fn (User $record): bool => $record->isSuspended()),
                // Optional: Add Policy check ->visible(fn (User $record): bool => $record->isSuspended() && auth()->user()->can('unsuspend', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
