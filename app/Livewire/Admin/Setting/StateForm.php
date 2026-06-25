<?php

namespace App\Livewire\Admin\Setting;

use Livewire\Component;
use App\Models\Country;
use App\Models\State;
use Livewire\Attributes\Rule;
use Jantinnerezo\LivewireAlert\LivewireAlert;

/**
 * Class StateForm
 *
 * Livewire component responsible for creating
 * and updating state records in the Admin
 * Settings section.
 *
 * This component handles:
 * - State creation
 * - State editing
 * - Country selection
 * - Form validation
 * - Success alerts
 *
 * @package App\Livewire\Admin\Setting
 */
class StateForm extends Component
{
    use LivewireAlert;

    /**
     * Selected country ID.
     *
     * @var int|string|null
     */
    #[Rule('required')]
    public $country;

    /**
     * State name.
     *
     * @var string|null
     */
    #[Rule('required')]
    public $name;

    /**
     * State status (active/inactive).
     *
     * @var int|string|null
     */
    #[Rule('required')]
    public $status;

    /**
     * State ID used for edit mode.
     *
     * @var int|string|null
     */
    public $stateEditId;

    /**
     * Lifecycle hook executed when the component is mounted.
     *
     * Loads state details for edit mode and
     * populates form fields accordingly.
     *
     * @param int|string|null $id State ID
     * @return void
     */
    public function mount($id)
    {
        $this->stateEditId = $id;

        $stateEdit = State::where('id', $this->stateEditId)->first();
        $this->country = $stateEdit->country_id;
        $this->name    = $stateEdit->name;
        $this->status  = $stateEdit->status;
    }

    /**
     * Handle state form submission.
     *
     * Validates input data and performs:
     * - State update (if editing)
     * - State creation (if new)
     *
     * Displays success alerts and redirects
     * back to the states listing page.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function submitState()
    {
        $this->validate();

        $data = [
            'country_id' => $this->country,
            'name'       => $this->name,
            'status'     => $this->status,
        ];

        if ($this->stateEditId != null) {
            State::where('id', $this->stateEditId)->update($data);
            $this->alert('success', 'State updated successfully');
        } else {
            State::create($data);
            $this->alert('success', 'State created successfully');
        }

        return redirect(url('/admin/setting/states'));
    }

    /**
     * Render the Livewire component view.
     *
     * Loads country list for state form
     * and displays the create/edit form.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        $countries = Country::get();

        return view('livewire.admin.setting.state-form', [
            'countries' => $countries,
        ]);
    }
}
