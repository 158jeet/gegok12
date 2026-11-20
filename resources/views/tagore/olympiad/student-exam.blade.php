<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $event->title }} | Exam</title>
<link rel="stylesheet" href="{{ asset('tagore-erp.css') }}">
<style>
.tg-exam-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.tg-timer{font-size:20px;font-weight:850;font-variant-numeric:tabular-nums;padding:10px 14px;border:1px solid var(--tg-line);border-radius:10px}
.tg-question-card{padding:16px;border:1px solid var(--tg-line);border-radius:12px;margin:12px 0}
.tg-answer{display:flex;align-items:flex-start;gap:10px;padding:10px;border:1px solid var(--tg-line);border-radius:9px;margin:8px 0;cursor:pointer}
.tg-answer input{margin-top:4px}
#autosave-status{font-size:12px;color:var(--tg-muted);margin:10px 0}
</style>
</head>
<body class="tg-app">
<div class="tg-shell">
<header class="tg-hero">
  <div class="tg-exam-top">
    <div>
      <div class="tg-eyebrow">TIMED ASSESSMENT</div>
      <h1>{{ $event->title }}</h1>
      <p>Registration {{ $registration->registration_no }} · {{ $questions->count() }} questions</p>
    </div>
    <div class="tg-timer" id="exam-timer" role="timer" aria-live="polite">--:--</div>
  </div>
</header>
<div class="tg-card" style="margin:14px 0">
  <p>@if(isset($parentExamToken))<b>Parent-supervised assessment for {{ $child->name }}.</b> @endif<b>Instructions:</b> choose one answer per question. Scores are calculated on the server from approved questions. Your answers are saved automatically while you work.</p>
</div>
<form id="exam-form" method="post"
  action="{{ isset($parentExamToken) ? route('tagore.parent.olympiad.submit',['studentId'=>$studentId,'olympiadId'=>$event->id,'token'=>$parentExamToken]) : route('tagore.olympiad.submit',$event->id) }}"
  data-autosave-url="{{ isset($parentExamToken) ? route('tagore.parent.olympiad.answers.save',['studentId'=>$studentId,'olympiadId'=>$event->id,'token'=>$parentExamToken]) : route('tagore.olympiad.answers.save',$event->id) }}">
  @csrf
  @foreach($questions as $question)
    <section class="tg-question-card">
      <h2 style="font-size:16px">Question {{ $loop->iteration }} <span class="tg-kpi-meta">({{ $question->marks }} mark(s))</span></h2>
      <p>{{ $question->question_text }}</p>
      @foreach($question->options as $key=>$option)
        <label class="tg-answer">
          <input type="radio" name="answers[{{ $question->id }}]" value="{{ $key }}" @checked(($savedAnswers[(string) $question->id] ?? null) === $key)>
          <span><b>{{ $key }}.</b> {{ $option }}</span>
        </label>
      @endforeach
    </section>
  @endforeach
  <p id="autosave-status" role="status" aria-live="polite">Answers are saved automatically every few seconds.</p>
  <div class="tg-hero-actions"><button class="tg-btn primary" type="submit" id="submit-exam">Submit exam</button></div>
</form>
</div>
<script>
(() => {
  const form = document.getElementById('exam-form');
  const timer = document.getElementById('exam-timer');
  const status = document.getElementById('autosave-status');
  const submitButton = document.getElementById('submit-exam');
  const csrf = form.querySelector('input[name="_token"]').value;
  const autosaveUrl = form.dataset.autosaveUrl;
  let seconds = {{ (int)$remainingSeconds }};
  let submitted = false;
  let dirty = false;
  let saving = false;
  let debounceHandle;

  const fmt = n => String(n).padStart(2, '0');
  const collectAnswers = () => {
    const answers = {};
    for (const [name, value] of new FormData(form).entries()) {
      const match = name.match(/^answers\[(\d+)\]$/);
      if (match) answers[match[1]] = value;
    }
    return answers;
  };

  const saveAnswers = async (keepalive = false) => {
    if (submitted || saving || !dirty) return;
    saving = true;
    dirty = false;
    status.textContent = 'Saving answers…';
    try {
      const response = await fetch(autosaveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf
        },
        body: JSON.stringify({ answers: collectAnswers() })
      });
      if (!response.ok) throw new Error('Autosave rejected');
      status.textContent = 'All answers saved';
    } catch (error) {
      dirty = true;
      status.textContent = 'Autosave unavailable — keep this page open and submit before the timer ends.';
    } finally {
      saving = false;
    }
  };

  form.addEventListener('change', () => {
    dirty = true;
    status.textContent = 'Unsaved changes…';
    clearTimeout(debounceHandle);
    debounceHandle = setTimeout(() => saveAnswers(), 700);
  });

  const fmtTimer = () => {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    timer.textContent = fmt(m) + ':' + fmt(s);
  };

  const tick = () => {
    fmtTimer();
    if (seconds <= 0) {
      if (!submitted) {
        submitted = true;
        status.textContent = 'Time ended — submitting your answers…';
        form.submit();
      }
      return;
    }
    seconds -= 1;
  };

  form.addEventListener('submit', () => {
    submitted = true;
    clearTimeout(debounceHandle);
    submitButton.disabled = true;
    submitButton.textContent = 'Submitting…';
  });

  window.addEventListener('pagehide', () => {
    if (dirty && !saving && !submitted) saveAnswers(true);
  });

  tick();
  const timerHandle = setInterval(() => {
    tick();
    if (seconds <= 0) clearInterval(timerHandle);
  }, 1000);
  setInterval(() => {
    if (dirty) saveAnswers();
  }, 15000);
})();
</script>
</body>
</html>
