<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('lab.queue.receiving', function ($user) {
    return $user->hasRole(['receiving', 'admin']);
});

Broadcast::channel('lab.queue.analyst', function ($user) {
    return $user->hasRole(['analyst', 'admin']);
});

Broadcast::channel('lab.queue.head', function ($user) {
    return $user->hasRole(['head_analysis', 'admin']);
});
