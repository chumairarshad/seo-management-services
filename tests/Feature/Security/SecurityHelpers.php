<?php

namespace Tests\Feature\Security;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectOwnershipService;
use ReflectionClass;
use ReflectionProperty;

class SecurityHelpers
{
    public static function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function project(User $owner, array $overrides = []): Project
    {
        $project = Project::factory()->create($overrides);
        app(ProjectOwnershipService::class)->sync($project, [
            ['user_id' => $owner->id, 'share_bps' => 10000],
        ]);

        return $project;
    }

    /**
     * What a Livewire component actually ships to the browser as state.
     *
     * Every public property is serialised into `wire:snapshot` and echoed back on
     * each request, so this is the surface that must never hold a secret.
     */
    public static function serialisedState(object $component): string
    {
        $values = [];

        foreach ((new ReflectionClass($component))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $values[$property->getName()] = $property->isInitialized($component)
                ? $property->getValue($component)
                : null;
        }

        return (string) json_encode($values);
    }
}
