<?php

namespace App\Livewire\Conversations;

use Livewire\Component;
use Illuminate\Support\Str;
use App\Events\Conversations\ConversationCreated;
use App\Models\Conversation;

/**
 * Class ConversationCreate
 *
 * Livewire component responsible for creating
 * a new conversation and sending the first message.
 *
 * This component handles:
 * - Selecting users for the conversation
 * - Creating a conversation record
 * - Creating the initial message
 * - Attaching users to the conversation
 * - Broadcasting the conversation creation event
 *
 * @package App\Livewire\Conversations
 */
class ConversationCreate extends Component
{
    /**
     * List of selected users for the conversation.
     *
     * @var array
     */
    public $users = [];

    /**
     * Initial message body for the conversation.
     *
     * @var string
     */
    public $body = '';

    /**
     * Add a user to the conversation participant list.
     *
     * @param mixed $user User model or user data
     * @return void
     */
    public function addUser($user)
    {
        $this->users[] = $user;
    }

    /**
     * Create a new conversation.
     *
     * Validates input data, creates a conversation record,
     * sends the initial message, attaches users, broadcasts
     * the creation event, and redirects to the conversation view.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function create()
    {
        $this->validate([
            'body'  => 'required',
            'users' => 'required',
        ]);

        $conversation = Conversation::create([
            'uuid' => Str::uuid(),
        ]);

        $conversation->messages()->create([
            'user_id' => auth()->id(),
            'body'    => $this->body,
        ]);

        $conversation->users()->sync($this->collectUserIds());

        broadcast(new ConversationCreated($conversation))->toOthers();

        return redirect()->route('admin.conversations.show', $conversation);
    }

    /**
     * Collect unique user IDs for the conversation.
     *
     * Merges selected users with the authenticated user
     * and returns a unique list of user IDs.
     *
     * @return \Illuminate\Support\Collection
     */
    public function collectUserIds()
    {
        return collect($this->users)
            ->merge([auth()->user()])
            ->pluck('id')
            ->unique();
    }

    /**
     * Render the Livewire component view.
     *
     * Displays the conversation creation form.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.conversations.conversation-create');
    }
}
