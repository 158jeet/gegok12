<?php

namespace App\Livewire\Admin\Setting;

use Livewire\Component;
use App\Models\Country;

/**
 * Class CountryDetail
 *
 * Livewire component responsible for displaying
 * detailed information of a single country
 * in the Admin Settings section.
 *
 * @package App\Livewire\Admin\Setting
 */
class CountryDetail extends Component
{
    /**
     * Country identifier used to fetch country details.
     *
     * @var int|string
     */
    public $countryDetailId;

    /**
     * Lifecycle hook executed when the component is mounted.
     *
     * Assigns the country identifier received from the route
     * to the component property.
     *
     * @param int|string $id Country ID
     * @return void
     */
    public function mount($id)
    {
        $this->countryDetailId = $id;
    }

    /**
     * Render the Livewire component view.
     *
     * Fetches country details based on the provided ID
     * and passes the data to the Blade view.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        $countryDetail = Country::where('id', $this->countryDetailId)->first();

        return view('livewire.admin.setting.country-detail', [
            'countryDetail' => $countryDetail,
        ]);
    }
}
