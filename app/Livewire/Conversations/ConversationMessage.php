<?php

namespace App\Livewire\Conversations;

use Livewire\Component;
use App\Models\Message;

/**
 * Class ConversationMessage
 *
 * Livewire component responsible for rendering
 * a single message within a conversation.
 *
 * @package App\Livewire\Conversations
 */
class ConversationMessage extends Component
{
    /**
     * Message instance to be displayed.
     *
     * @var \App\Models\Message
     */
    public $message;

    /**
     * Lifecycle hook executed when the component is mounted.
     *
     * Assigns the message model instance to the component.
     *
     * @param \App\Models\Message $message
     * @return void
     */
    public function mount(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Render the Livewire component view.
     *
     * Displays the conversation message.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.conversations.conversation-message');
    }
}
