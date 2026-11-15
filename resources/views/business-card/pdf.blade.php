<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; color: #1e293b; }
        .card { border-radius: 12px; padding: 30px; max-width: 400px; margin: 40px auto; text-align: center; }
        h1 { font-size: 20px; margin: 10px 0 2px; }
        .title { color: #64748b; font-size: 13px; margin: 0; }
        .company { font-size: 13px; font-weight: bold; margin: 4px 0 16px; }
        .row { font-size: 12px; color: #334155; margin: 4px 0; }
        .qr { margin-top: 20px; }
        img.photo { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; }
        img.logo { max-height: 50px; max-width: 220px; margin: 0 auto 14px; object-fit: contain; }
    </style>
</head>
<body>
    {{-- Border/name/company use the card's own primary color inline
         rather than a gradient - DomPDF's support for CSS gradients is
         historically unreliable, so a solid color is the safer choice
         here even though the web/public page can use one freely. --}}
    <div class="card" style="border: 2px solid {{ $card->cardColor() }};">
        @if ($card->logoDataUri())
            <img src="{{ $card->logoDataUri() }}" class="logo" alt="">
        @endif
        @if ($card->photoDataUri())
            <img src="{{ $card->photoDataUri() }}" class="photo" alt="">
        @endif
        <h1 style="color: {{ $card->cardColor() }};">{{ $card->name }}</h1>
        @if ($card->title)
            <p class="title">{{ $card->title }}</p>
        @endif
        @if ($card->company)
            <p class="company" style="color: {{ $card->cardColor() }};">{{ $card->company }}</p>
        @endif

        @if ($card->phone)<p class="row">{{ $card->phone }}</p>@endif
        @if ($card->email)<p class="row">{{ $card->email }}</p>@endif
        @if ($card->website)<p class="row">{{ $card->website }}</p>@endif
        @if ($card->address)<p class="row">{{ $card->address }}</p>@endif

        <div class="qr">
            @if ($card->qrCodeDataUri(220))
                <img src="{{ $card->qrCodeDataUri(220) }}" alt="QR code" width="140" height="140">
            @endif
            <p class="row">{{ $card->publicUrl() }}</p>
        </div>
    </div>
</body>
</html>
