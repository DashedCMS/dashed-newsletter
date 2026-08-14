@if(count($links))
    <tr><td align="center" style="padding:16px 24px;">
        @foreach($links as $link)
            <a href="{{ $link['url'] }}" style="display:inline-block;margin:0 6px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#52525b;text-decoration:none;">{{ $link['label'] }}</a>
        @endforeach
    </td></tr>
@endif
