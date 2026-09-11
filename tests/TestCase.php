<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Mark the password as just confirmed for this request.
     *
     * The most damaging endpoints sit behind a step-up check (see
     * `StepUpAuthenticationTest`). A test whose subject is something else —
     * validation, authorization, branch isolation — should not have to walk
     * through the confirmation screen to reach the behaviour it is about, so it
     * says here that the step-up already happened.
     *
     * Tests that are about the step-up itself deliberately do not use this.
     */
    protected function withConfirmedPassword(): static
    {
        return $this->withSession(['auth.password_confirmed_at' => time()]);
    }
}
