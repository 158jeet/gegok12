@if (count($messages))
  <div class="tg-conversation" aria-label="Feedback conversation">
    @foreach($messages as $message)
      @php
        $isParentMessage = (int) $message->feedback->parent_id === (int) $message->user_id;
      @endphp
      <article class="tg-message {{ $isParentMessage ? 'from-parent' : 'from-school' }}">
        <div class="tg-message-meta">
          <strong>{{ $isParentMessage ? 'Parent / guardian' : 'Tagore school team' }}</strong>
          <time datetime="{{ optional($message->created_at)->toIso8601String() }}">{{ optional($message->created_at)->diffForHumans() }}</time>
        </div>
        @if($message->category)
          <span class="tg-message-category">{{ ucwords(str_replace('_', ' ', str_replace('/', ' / ', $message->category))) }}</span>
        @endif
        <p class="tg-message-body">{{ $message->message }}</p>
        @if($message->FilePath && count($message->FilePath))
          <div class="tg-message-attachments">
            @foreach($message->FilePath as $file)
              <a href="{{ $file }}" target="_blank" rel="noopener noreferrer">
                <img src="{{ $file }}" alt="Feedback attachment" loading="lazy">
              </a>
            @endforeach
          </div>
        @endif
      </article>
    @endforeach
  </div>
@endif
