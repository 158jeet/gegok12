<!doctype html>
<html lang="{{ $page->locale ?? 'en' }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $page->meta_title ?: $page->title }}</title><meta name="description" content="{{ $page->meta_description }}"><meta name="robots" content="{{ $preview ? 'noindex,nofollow' : 'index,follow' }}"><style>
:root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#17243b;background:#f5f7fb}*{box-sizing:border-box}body{margin:0}.bar{background:#102b50;color:white;padding:18px max(22px,calc((100% - 1120px)/2));display:flex;justify-content:space-between;gap:20px}.brand{font-weight:800;letter-spacing:.02em}.wrap{max-width:1120px;margin:42px auto;padding:0 22px}.preview{background:#fff4d8;color:#694b0b;padding:12px 20px;text-align:center;font-weight:700}.hero{padding:56px 48px;border-radius:24px;background:linear-gradient(120deg,#102b50,#24618a);color:#fff}.hero h1{font-size:clamp(2rem,5vw,3.8rem);line-height:1.08;margin:0 0 16px}.hero p{font-size:1.12rem;line-height:1.7;max-width:760px}.section{background:#fff;border:1px solid #e4eaf2;border-radius:18px;padding:28px;margin:20px 0;box-shadow:0 12px 35px #102b500d}.section h2{margin-top:0}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px}.stat{background:#f1f5fa;border-radius:14px;padding:20px}.stat b{display:block;font-size:1.8rem;color:#174c77}.cta a{display:inline-block;background:#d3a94f;color:#14243a;text-decoration:none;padding:13px 20px;border-radius:999px;font-weight:700}.footer{text-align:center;color:#65758a;padding:28px}@media(max-width:640px){.wrap{margin:20px auto}.hero{padding:32px 24px}.section{padding:22px}}
</style></head><body>
@if($preview)<div class="preview">PRIVATE DRAFT PREVIEW — this page is not publicly published.</div>@endif
<header class="bar"><div class="brand">{{ $site->site_name ?? 'Tagore Website Preview' }}</div><div>{{ $page->locale === 'hi' ? 'आधिकारिक वेबसाइट' : 'Official Website' }}</div></header>
<main class="wrap"><h1>{{ $page->title }}</h1>
@php($blocks = is_array($page->content_json) ? $page->content_json : (json_decode($page->content_json ?? '[]', true) ?: []))
@if(count($blocks) === 0)<section class="section"><p style="white-space:pre-line">{{ $page->body }}</p></section>@endif
@foreach($blocks as $block)
@if(($block['type'] ?? '') === 'hero')<section class="hero"><h1>{{ $block['heading'] ?? $page->title }}</h1><p>{{ $block['body'] ?? '' }}</p></section>
@elseif(($block['type'] ?? '') === 'text')<section class="section"><h2>{{ $block['heading'] ?? '' }}</h2><p style="white-space:pre-line;line-height:1.8">{{ $block['body'] ?? '' }}</p></section>
@elseif(($block['type'] ?? '') === 'stats')<section class="section"><div class="stats"><div class="stat"><b>{{ $block['value'] ?? '' }}</b>{{ $block['label'] ?? '' }}</div></div></section>
@elseif(($block['type'] ?? '') === 'cta')<section class="section cta"><h2>{{ $block['heading'] ?? '' }}</h2><p>{{ $block['body'] ?? '' }}</p>@if(!empty($block['button_url']))<a href="{{ $block['button_url'] }}" rel="noopener">{{ $block['button_label'] ?? 'Learn more' }}</a>@endif</section>
@elseif(($block['type'] ?? '') === 'faq')<section class="section"><h2>{{ $block['question'] ?? '' }}</h2><p>{{ $block['answer'] ?? '' }}</p></section>@endif
@endforeach
@if(count($blocks)>0 && $page->body)<section class="section"><p style="white-space:pre-line">{{ $page->body }}</p></section>@endif
</main><footer class="footer">© {{ date('Y') }} {{ $site->site_name ?? 'Tagore' }}</footer></body></html>
