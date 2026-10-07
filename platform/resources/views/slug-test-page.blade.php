<!DOCTYPE html>
<html>
<head>
    <title>Exam Slug Test - ExamElite</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f0f0f0; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 20px; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #0d9488; color: white; }
        .btn { background: #0d9488; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px; font-size: 12px; }
        .btn:hover { background: #0f766e; }
        .slug { font-family: monospace; color: #0d9488; }
        .old-url { color: #dc2626; text-decoration: line-through; }
        .new-url { color: #10b981; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔗 Exam URL Slug Test</h1>
        <p>This page shows the difference between OLD (ID-based) and NEW (Slug-based) URLs</p>
        
         <div class="alert alert-info" style="background:#e0f2fe; padding:15px; border-radius:5px; margin-bottom:20px;">
            ✅ <strong>Fix Applied:</strong> All exam buttons now use <strong>SLUG</strong> instead of ID in the URL
        </div>
        
        <h2>📋 Exam List (Slugs Generated)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Exam Name</th>
                    <th>Slug (URL friendly)</th>
                    <th>OLD URL (Broken)</th>
                    <th>NEW URL (Working)</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $exams = App\Models\Exam::where('status', 'Active')->limit(20)->get();
                @endphp
                @foreach($exams as $exam)
                <tr>
                    <td>{{ $exam->id }}</td>
                    <td>{{ $exam->name }}</td>
                    <td class="slug">{{ $exam->slug }}</td>
                    <td class="old-url">/guest/exam/guideline/{{ $exam->slug }}</td>
                    <td class="new-url">
                        <a href="/guest/exam/guideline/{{ $exam->slug }}" class="btn">Test Slug URL →</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <hr>
        <h3>🎯 How to Verify the Fix</h3>
        <ol>
            <li>Click any <strong>"Test Slug URL"</strong> button above</li>
            <li>Check the browser URL bar - it should show <strong>slug text, not ID number</strong></li>
            <li>The exam guideline page should load properly</li>
            <li>Refresh the page - it still works!</li>
        </ol>
        
        <p><strong>Example:</strong><br>
        ❌ Old: <code>/guest/exam/guideline/{{ $exam->slug }}</code><br>
        ✅ New: <code>/guest/exam/guideline/neet-full-length-mock-test-1</code>
        </p>
    </div>
</body>
</html>
