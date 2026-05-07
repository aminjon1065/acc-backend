<?php

// Public self-registration is intentionally disabled in this application —
// see config/fortify.php (`Features::registration()` is commented out with
// the note "only admin can create users"). The two scaffolded tests below
// were inherited from the Laravel starter and would fail with
// RouteNotFoundException because the `register` and `register.store` routes
// no longer exist.
//
// Skipping rather than deleting per project policy. If self-registration is
// ever re-enabled, remove these markers and the tests will run as-is.

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
})->skip('Self-registration disabled in config/fortify.php; admin-only user creation.');

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
})->skip('Self-registration disabled in config/fortify.php; admin-only user creation.');
