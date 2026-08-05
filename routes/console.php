<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('subscriptions:expire')->hourly();
Schedule::command('chunked-video-uploads:cleanup')->daily();
