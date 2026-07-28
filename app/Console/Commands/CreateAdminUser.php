<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
        {--name= : Full name of the administrator}
        {--email= : Unique administrator email}
        {--phone= : Unique administrator phone number}
        {--password= : Password; generated in non-interactive mode when omitted}
        {--force : Update an existing matching user and promote it to administrator}';

    protected $description = 'Create or promote an active Filament dashboard administrator';

    public function handle(): int
    {
        $interactive = $this->input->isInteractive();
        $generatedPassword = false;

        $name = trim((string) ($this->option('name') ?: ($interactive
            ? $this->ask('اسم المدير', 'مدير النظام')
            : 'مدير النظام')));
        $email = trim((string) ($this->option('email') ?: ($interactive
            ? $this->ask('البريد الإلكتروني')
            : '')));
        $phone = trim((string) ($this->option('phone') ?: ($interactive
            ? $this->ask('رقم الهاتف')
            : '')));
        $password = (string) ($this->option('password') ?: ($interactive
            ? $this->secret('كلمة المرور')
            : ''));

        if ($password === '') {
            $password = Str::password(20);
            $generatedPassword = true;
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $matches = User::query()
            ->where('phone', $phone)
            ->orWhere('email', $email)
            ->get();

        if ($matches->count() > 1) {
            $this->error('رقم الهاتف والبريد الإلكتروني مرتبطان بحسابين مختلفين.');

            return self::FAILURE;
        }

        $user = $matches->first();

        if ($user && ! $this->option('force')) {
            $this->error('يوجد مستخدم بنفس الهاتف أو البريد. استخدم --force لترقيته وتحديثه.');

            return self::FAILURE;
        }

        $created = ! $user;

        $user = DB::transaction(function () use ($user, $name, $email, $phone, $password): User {
            $user ??= new User();

            $user->fill([
                'full_name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'phone_verified_at' => $user->phone_verified_at ?? now(),
                'status' => 'active',
                'is_admin' => true,
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);

            $user->save();

            return $user->fresh();
        });

        $this->newLine();
        $this->info($created ? 'تم إنشاء حساب المدير بنجاح.' : 'تم تحديث المستخدم وترقيته إلى مدير.');
        $this->table(['الحقل', 'القيمة'], [
            ['المعرف', $user->id],
            ['الاسم', $user->full_name],
            ['البريد الإلكتروني', $user->email],
            ['رقم الهاتف', $user->phone],
            ['مسار لوحة التحكم', rtrim((string) config('app.url'), '/').'/admin'],
        ]);

        if ($generatedPassword) {
            $this->warn('تم إنشاء كلمة مرور تلقائيًا. انسخها الآن لأنها لن تظهر مرة أخرى:');
            $this->line($password);
        }

        return self::SUCCESS;
    }
}
