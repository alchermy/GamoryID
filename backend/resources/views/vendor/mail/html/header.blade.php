@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'GamoryID' || trim($slot) === config('app.name'))
Gamory<span class="brand-accent">ID</span>
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
