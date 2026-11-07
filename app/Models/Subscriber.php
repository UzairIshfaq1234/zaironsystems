<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A newsletter sign-up. */
class Subscriber extends Model
{
    protected $fillable = ['email', 'ip_address'];

    protected $hidden = ['ip_address'];
}
