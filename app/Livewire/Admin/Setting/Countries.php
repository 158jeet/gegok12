<?php

namespace App\Livewire\Admin\Setting;

use Livewire\Component;
use App\Models\Country;
use Livewire\WithPagination;

/**
 * Class Countries
 *
 * Livewire component responsible for managing
 * and displaying the list of countries in the
 * Admin Settings section.
 *
 * Features:
 * - Search countries by name
 * - Paginated country listing
 *
 * @package App\Livewire\Admin\Setting
 */
class Countries extends Component
{
    use WithPagination;

    /**
     * Search keyword used to filter countries by name.
     *
     * @var string
     */
    public $search = '';

    /**
     * Render the Livewire component view.
     *
     * Builds the country query with optional
     * search filtering and returns paginated results.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        $countries = Country::query();

        if ($this->search) {
            $countries = $countries->where(function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            });
        }

        $countries = $countries->paginate(10);

        return view('livewire.admin.setting.countries', [
            'countries' => $countries
        ]);
    }

    /**
     * Livewire hook triggered when the search property is updated.
     *
     * Resets pagination to the first page whenever
     * the search keyword changes.
     *
     * @return void
     */
    public function updatedSearch()
    {
        $this->resetPage();
    }
}
