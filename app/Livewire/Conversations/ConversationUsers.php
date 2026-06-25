<?php

namespace App\Livewire\Conversations;

use Livewire\Component;
use Illuminate\Support\Collection;

/**
 * Class ConversationUsers
 *
 * Livewire component responsible for displaying
 * the list of users participating in a conversation.
 *
 * @package App\Livewire\Conversations
 */
class ConversationUsers extends Component
{
    /**
     * Collection of users participating in the conversation.
     *
     * @var \Illuminate\Support\Collection
     */
    public $users;

    /**
     * Lifecycle hook executed when the component is mounted.
     *
     * Injects the collection of conversation users
     * into the component.
     *
     * @param \Illuminate\Support\Collection $users
     * @return void
     */
    public function mount(Collection $users)
    {
        $this->users = $users;
    }

    /**
     * Render the Livewire component view.
     *
     * Displays the conversation users list.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.conversations.conversation-users');
    }
}
