<?php

namespace App\Livewire\Admin\Setting;

use Livewire\Component;
use App\Models\Smstemplate;
use Livewire\WithPagination;
use Jantinnerezo\LivewireAlert\LivewireAlert;

/**
 * Class SmsTemplates
 *
 * Livewire component responsible for displaying
 * a paginated list of SMS templates in the
 * Admin Settings section.
 *
 * Features:
 * - Paginated SMS template listing
 * - Sorted by latest templates first
 *
 * @package App\Livewire\Admin\Setting
 */
class SmsTemplates extends Component
{
    use WithPagination;

    /**
     * Render the Livewire component view.
     *
     * Fetches paginated SMS templates ordered
     * by descending ID and passes the data
     * to the Blade view.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        $sms_templates = Smstemplate::orderBy('id', 'desc')->paginate(10);

        return view('livewire.admin.setting.sms-templates', [
            'sms_templates' => $sms_templates
        ]);
    }
}
