<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => \App\Enums\UserRole::class,
            'password' => 'hashed',
        ];
    }

    public function hasRole(\App\Enums\UserRole|string ...$roles): bool
    {
        return in_array($this->role, array_map(
            static fn (\App\Enums\UserRole|string $role): \App\Enums\UserRole => $role instanceof \App\Enums\UserRole ? $role : \App\Enums\UserRole::from($role),
            $roles,
        ), true);
    }

    public function canManageRole(\App\Enums\UserRole|string $target): bool
    {
        $targetRole = $target instanceof \App\Enums\UserRole ? $target : \App\Enums\UserRole::from($target);

        return $this->role->canManage($targetRole);
    }
}
