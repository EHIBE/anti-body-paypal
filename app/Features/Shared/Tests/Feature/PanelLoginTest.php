<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('shows the same login error for an unknown email and for a wrong password', function (): void {
    $user = User::factory()->create();
    $unknownEmailMessages = [];
    $wrongPasswordMessages = [];

    $unknownEmailComponent = Livewire::test(Login::class);
    $unknownEmailComponent->set('data.email', 'nobody@example.test');
    $unknownEmailComponent->set('data.password', 'password');
    $unknownEmailComponent->call('authenticate');
    $unknownEmailComponent->assertHasErrors([
        'data.email' => function (array $rules, array $messages) use (&$unknownEmailMessages): bool {
            $unknownEmailMessages = $messages;

            return true;
        },
    ]);

    $wrongPasswordComponent = Livewire::test(Login::class);
    $wrongPasswordComponent->set('data.email', $user->email);
    $wrongPasswordComponent->set('data.password', 'not-the-password');
    $wrongPasswordComponent->call('authenticate');
    $wrongPasswordComponent->assertHasErrors([
        'data.email' => function (array $rules, array $messages) use (&$wrongPasswordMessages): bool {
            $wrongPasswordMessages = $messages;

            return true;
        },
    ]);

    expect($unknownEmailMessages)->not->toBeEmpty()
        ->and($wrongPasswordMessages)->toBe($unknownEmailMessages);
    assertGuest();
});

it('does not reveal in the login error whether the account exists', function (): void {
    $user = User::factory()->create();
    $messages = [];

    $component = Livewire::test(Login::class);
    $component->set('data.email', $user->email);
    $component->set('data.password', 'not-the-password');
    $component->call('authenticate');
    $component->assertHasErrors([
        'data.email' => function (array $rules, array $errorMessages) use (&$messages): bool {
            $messages = $errorMessages;

            return true;
        },
    ]);

    $text = mb_strtolower(json_encode($messages, JSON_THROW_ON_ERROR));

    expect($text)->not->toBeEmpty();
    expect($text)->not->toContain(mb_strtolower($user->email));
    expect($text)->not->toContain('exist');
    expect($text)->not->toContain('unknown');
    expect($text)->not->toContain('incorrect password');
    expect($text)->not->toContain('wrong password');
});

it('signs in a user with valid credentials', function (): void {
    $user = User::factory()->create();

    $component = Livewire::test(Login::class);
    $component->set('data.email', $user->email);
    $component->set('data.password', 'password');
    $component->call('authenticate');
    $component->assertHasNoErrors();

    assertAuthenticatedAs($user);
});
