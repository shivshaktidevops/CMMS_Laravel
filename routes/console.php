<?php
use Illuminate\Support\Facades\Artisan;
Artisan::command('cmms:about', function () { $this->info('Plant CMMS Laravel/MySQL migration foundation'); });
