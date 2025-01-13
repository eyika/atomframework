<?php

namespace Eyika\Atom\Framework\Support\Auth;

use Eyika\Atom\Framework\Support\Auth\Concerns\Authenticatable;
use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Database\Concerns\UserAwareQueryBuilder;
use Eyika\Atom\Framework\Support\Database\Model;

class User extends Model implements AuthenticatableInterface
{
    use Authenticatable, UserAwareQueryBuilder;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
