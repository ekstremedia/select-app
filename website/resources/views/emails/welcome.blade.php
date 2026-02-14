<x-mail::message>
# Welcome to SELECT, {{ $user->nickname ?? $user->name }}!

Thanks for joining SELECT - the acronym sentence game.

Create or join a game with friends and start crafting sentences from random acronyms. The best answers get the most votes!

<x-mail::button :url="config('app.url')">
Play Now
</x-mail::button>

Have fun!<br>
{{ config('app.name') }}
</x-mail::message>
