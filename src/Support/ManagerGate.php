<?php

namespace Spiggle\FilamentPortalSnapshot\Support;

use Spiggle\FilamentVisualBoard\Support\CurrentWorkspace;

class ManagerGate
{
    public static function allows(): bool
    {
        if (! class_exists(CurrentWorkspace::class)) {
            return true;
        }

        return CurrentWorkspace::manages();
    }
}
