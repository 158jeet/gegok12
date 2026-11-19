<div class="tg-table-wrap">
  <table class="table tg-feedback-table" id="messagelist">
    <thead>
      <tr><th>From</th><th>Category</th><th>Message preview</th><th>Received</th><th>Status</th><th>Action</th></tr>
    </thead>
    <tbody>
      @forelse($feedbacks as $feedback)
        @php
          $latestMessage = $feedback->latestMessage;
          $firstMessage = $feedback->feedbackMessage->first();
          $messageText = $firstMessage?->message ?? 'No message content';
        @endphp
        <tr>
          <td>
            <a href="{{ url('/admin/parent/show/'.$feedback->parent->name) }}" class="tg-table-primary">{{ ucfirst($feedback->parent->FullName) }}</a>
          </td>
          <td>{{ ucwords(str_replace('_', ' ', str_replace('/', ' / ', $latestMessage->category ?? 'General'))) }}</td>
          <td class="tg-feedback-preview">{{ \Illuminate\Support\Str::limit($messageText, 90, '…') }}</td>
          <td>{{ optional($feedback->created_at)->format('d M Y, h:i A') }}</td>
          <td>
            @if($latestMessage && $latestMessage->is_seen == '0')
              <span class="tg-status warning">New</span>
            @elseif($latestMessage && $latestMessage->is_seen == 'has_seen')
              <span class="tg-status">Seen · action needed</span>
            @else
              <span class="tg-status success">Action taken</span>
            @endif
          </td>
          <td><a href="{{ url('admin/feedback/edit/'.$feedback->id) }}" class="tg-btn">Open conversation →</a></td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="tg-empty"><strong>No feedback found</strong><span>Try another search, or check again when a parent sends a message.</span></div></td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div class="tg-pagination">{{ $feedbacks->links() }}</div>
