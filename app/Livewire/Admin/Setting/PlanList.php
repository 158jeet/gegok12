<?php

namespace App\Livewire\Admin\Setting;

use Livewire\Component;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\TextInput;
use App\Models\Plan;

/**
 * Class PlanList
 *
 * Livewire component responsible for displaying
 * a list of plans using Filament Tables
 * in the Admin Settings section.
 *
 * Features:
 * - Paginated plan listing
 * - Searchable & sortable columns
 * - Create plan action
 * - Edit plan action
 *
 * @package App\Livewire\Admin\Setting
 */
class PlanList extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    /**
     * Configure the Filament table for plans.
     *
     * Defines:
     * - Query source
     * - Columns
     * - Header actions
     * - Row actions
     * - Pagination options
     *
     * @param Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        $query = Plan::query();

        return $table
            ->query($query)
            ->headerActions([
                CreateAction::make(),
            ])
            ->columns([
                TextColumn::make('cycle'),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('order'),
                TextColumn::make('is_active'),
                TextColumn::make('amount'),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                Action::make('Edit')
                    ->url(fn (Plan $r): string => route('admin.setting.plan.update', ['id' => $r])),
            ])
            ->bulkActions([
                // ...
            ])
            ->paginated([10, 25, 50, 100, 'all']);
    }

    /**
     * Render the Livewire component view.
     *
     * Displays the Filament-powered plan list table.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.admin.setting.plan-list');
    }
}
