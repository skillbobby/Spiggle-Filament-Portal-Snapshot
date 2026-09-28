<?php

use Spiggle\FilamentPortalSnapshot\Enums\ScheduleAction;

it('exposes labels for every action', function () {
    foreach (ScheduleAction::cases() as $action) {
        expect($action->label())->not->toBeEmpty()
            ->and($action->description())->not->toBeEmpty()
            ->and($action->icon())->toStartWith('heroicon-');
    }
});
