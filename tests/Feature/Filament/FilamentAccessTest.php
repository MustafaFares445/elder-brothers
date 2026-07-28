<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Panel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FilamentAccessTest extends TestCase
{
    #[Test]
    public function an_active_administrator_can_access_the_admin_panel(): void
    {
        $user = new User([
            'full_name' => 'Admin',
            'status' => 'active',
            'is_admin' => true,
        ]);

        $this->assertTrue($user->canAccessPanel(Panel::make()->id('admin')));
    }

    #[Test]
    public function a_student_cannot_access_the_admin_panel(): void
    {
        $user = new User([
            'full_name' => 'Student',
            'status' => 'active',
            'is_admin' => false,
        ]);

        $this->assertFalse($user->canAccessPanel(Panel::make()->id('admin')));
    }

    #[Test]
    public function a_suspended_administrator_cannot_access_the_admin_panel(): void
    {
        $user = new User([
            'full_name' => 'Suspended Admin',
            'status' => 'suspended',
            'is_admin' => true,
        ]);

        $this->assertFalse($user->canAccessPanel(Panel::make()->id('admin')));
    }
}
