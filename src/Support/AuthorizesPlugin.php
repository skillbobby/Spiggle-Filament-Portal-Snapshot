<?php

namespace Spiggle\FilamentPortalSnapshot\Support;

use Spiggle\FilamentPortalSnapshot\FilamentPortalSnapshotPlugin;

trait AuthorizesPlugin
{
    public static function canAccess(): bool
    {
        try {
            return FilamentPortalSnapshotPlugin::get()->isAuthorized();
        } catch (\Throwable) {
            return false;
        }
    }
}
