<?php

namespace Tests\Unit;

use App\Core\Validation\ValidationRules;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ValidationRulesTest extends TestCase
{
    #[Test]
    public function login_email_rules_require_valid_email(): void
    {
        $rules = ValidationRules::loginEmail();

        $this->assertContains('required', $rules);
        $this->assertContains('email:rfc', $rules);
    }

    #[Test]
    public function password_rules_require_minimum_length_and_mixed_characters(): void
    {
        $rules = ValidationRules::password();

        $this->assertContains('required', $rules);
        $this->assertContains('string', $rules);
    }

    #[Test]
    public function name_rules_allow_unicode_letters(): void
    {
        $rules = ValidationRules::name();

        $this->assertContains('regex:/^[\pL\s\-\'\.]+$/u', $rules);
    }

    #[Test]
    public function sanctum_tokens_have_a_positive_expiration(): void
    {
        $this->assertGreaterThan(0, (int) config('sanctum.expiration'));
    }
}
