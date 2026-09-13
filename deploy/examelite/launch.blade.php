<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Open exam workspace</title>@include('layouts.head-css')</head>
<body><main class="container py-5"><div class="card mx-auto" style="max-width:640px"><div class="card-body"><h1 class="h3">Open your exam workspace</h1><p>Continue with the exam access link you opened from Tech4Learn.</p>
<form method="post" action="/tech4learn/launch">@csrf
<input type="hidden" name="ticket" id="ticket"><button class="btn btn-primary" id="continue" disabled>Continue to ExamElite</button>
</form><p id="message" class="mt-3">Checking the link…</p></div></div></main>
<script>
const ticket=location.hash.slice(1);history.replaceState(null,'',location.pathname);
if(/^[a-f0-9]{64}$/.test(ticket)){document.getElementById('ticket').value=ticket;document.getElementById('continue').disabled=false;document.getElementById('message').textContent='';}
else document.getElementById('message').textContent='Open a new exam access link from Tech4Learn.';
</script></body></html>
