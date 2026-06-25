<?php

namespace App\Livewire\Admin\Setting;

use Livewire\Component;
use App\Models\Plan;

/**
 * Class PlanDetail
 *
 * Livewire component responsible for displaying
 * detailed information of a single plan
 * in the Admin Settings section.
 *
 * @package App\Livewire\Admin\Setting
 */
class PlanDetail extends Component
{
    /**
     * Plan identifier used to fetch plan details.
     *
     * @var int|string
     */
    public $planDetailId;

    /**
     * Lifecycle hook executed when the component is mounted.
     *
     * Assigns the plan identifier received from the route
     * to the component property.
     *
     * @param int|string $id Plan ID
     * @return void
     */
    public function mount($id)
    {
        $this->planDetailId = $id;
    }

    /**
     * Render the Livewire component view.
     *
     * Fetches plan details based on the provided ID
     * and passes the data to the Blade view.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        $planDetail = Plan::where('id', $this->planDetailId)->first();

        return view('livewire.admin.setting.plan-detail', [
            'planDetail' => $planDetail,
        ]);
    }
}
