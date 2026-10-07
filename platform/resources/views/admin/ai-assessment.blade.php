@extends('layouts.master')
@section('title', 'AI Assessment')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h4>AI Subjective Answer Assessment</h4>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <strong>Pending Assessments:</strong> <span id="pendingCount">Loading...</span>
            </div>
            <button id="assessAllBtn" class="btn btn-primary">Assess All Pending</button>
            <button id="refreshBtn" class="btn btn-secondary">Refresh</button>
            <div id="result" class="mt-3"></div>
        </div>
    </div>
</div>

<script>
document.getElementById('assessAllBtn').onclick = function() {
    var btn = this;
    var resultDiv = document.getElementById('result');
    btn.disabled = true;
    resultDiv.innerHTML = '<div class="alert alert-info">Assessing all pending...</div>';
    
    fetch('/ai_assess_complete.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({bulk: true})
    })
    .then(r => r.json())
    .then(d => {
        resultDiv.innerHTML = '<div class="alert alert-success">✓ ' + d.total + ' answers processed</div>';
        btn.disabled = false;
    })
    .catch(e => {
        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + e.message + '</div>';
        btn.disabled = false;
    });
};

document.getElementById('refreshBtn').onclick = function() {
    location.reload();
};
</script>
@endsection
