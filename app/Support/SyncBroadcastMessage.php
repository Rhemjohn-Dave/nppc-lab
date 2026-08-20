<?php

namespace App\Support;

use Illuminate\Notifications\Messages\BroadcastMessage;

final class SyncBroadcastMessage
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function make(array $data): BroadcastMessage
    {
        return (new BroadcastMessage($data))->onConnection('sync');
    }
}
