<!DOCTYPE html>
<html>
<body style="font-family: sans-serif; padding: 24px;">
    <h2>{{ $title }}</h2>
    <p>Due {{ $dueAt->format('D, j M Y \a\t g:i A') }} for {{ $memberName }}.</p>
    @if($description)
        <p style="color: #444;">{{ $description }}</p>
    @endif
</body>
</html>
