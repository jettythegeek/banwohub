<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Conflict Check Report #{{ $check->id }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; padding: 24px; color: #1a1a1a; line-height: 1.5; }
        h1 { color: #0d6e6e; margin-bottom: 4px; }
        .meta { color: #555; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #e8f4f4; }
        .section { margin-top: 24px; }
    </style>
</head>
<body>
    <h1>Conflict Check Report</h1>
    <p class="meta">Generated {{ $report['generated_at'] ?? now()->toIso8601String() }}</p>

    <div class="section">
        <h2>Summary</h2>
        <table>
            <tr><th>Status</th><td>{{ $check->status }}</td></tr>
            <tr><th>Search terms</th><td>{{ implode(', ', $check->search_terms ?? []) }}</td></tr>
            <tr><th>Decision</th><td>{{ $check->decision ?? '—' }}</td></tr>
            <tr><th>Reviewer</th><td>{{ $check->reviewer?->name ?? '—' }}</td></tr>
            <tr><th>Reviewed at</th><td>{{ $check->reviewed_at ?? '—' }}</td></tr>
            <tr><th>Notes</th><td>{{ $check->notes ?? '—' }}</td></tr>
            @if($check->legalMatter)
            <tr><th>Related case</th><td>{{ $check->legalMatter->title }} ({{ $check->legalMatter->matter_number }})</td></tr>
            @endif
        </table>
    </div>

    @foreach($check->matches ?? [] as $bucket => $items)
    <div class="section">
        <h2>{{ ucfirst($bucket) }} matches ({{ count($items) }})</h2>
        @if(count($items))
        <table>
            <thead><tr><th>Details</th></tr></thead>
            <tbody>
                @foreach($items as $item)
                <tr><td><pre style="margin:0;white-space:pre-wrap;">{{ json_encode($item, JSON_PRETTY_PRINT) }}</pre></td></tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p>No matches in this category.</p>
        @endif
    </div>
    @endforeach
</body>
</html>
