<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function failStorageWrites(string $disk): void
    {
        $fake = Storage::fake($disk);
        $failing = \Mockery::mock($fake)->makePartial();

        $failing->shouldReceive('putFileAs')->once()->andReturn(false);

        Storage::set($disk, $failing);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
